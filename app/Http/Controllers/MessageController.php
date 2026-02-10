<?php

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\MasterfileModels\User;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    /**
     * Get all users for messaging
     */
    public function getUsers()
    {
        $currentUserId = Auth::id();

        // When in Tenant Mode (default), we should query users from the Tenant Database
        // But the relationship sentMessages might be looking at the 'messages' table.
        // We need to ensure that the 'messages' table exists in the Tenant Database.
        // If 'messages' is a system-wide feature (in 'mysql'), then we have a problem
        // because User model is now dynamically switching to Tenant DB for queries.
        
        // Assuming 'messages' table is in the Tenant Database (same as Users, Permissions).
        // If not, we might need to adjust the Message model connection.
        
        // The error 500 usually implies a SQL error or logic error.
        // Let's check the query construction.
        
        // Problem: 'User::where' uses the default connection (Tenant).
        // But 'sentMessages' relationship relies on the Message model.
        // If Message model has no connection defined, it also uses default (Tenant).
        // This looks correct IF both tables exist in Tenant DB.
        
        // HOWEVER, we previously had issues with User model relationships.
        // 'withCount' uses subqueries.
        
        // Let's optimize this and ensure it works with the current architecture.
        // We can remove the complex subqueries if they are causing issues across connections
        // or ensure they are using the correct table names/connections.
        
        $users = User::where('id', '!=', $currentUserId)
            ->where('name', '!=', 'Administrator')
            ->select([
                'id',
                'name',
                'role',
                'last_seen_at',
                'is_online'
            ])
            ->get()
            ->map(function ($user) use ($currentUserId) {
                // Calculate unread count manually or via separate query to avoid subquery complexity
                // if cross-connection is an issue (though here it should be same DB).
                
                // Let's use the relationship if it works, or query directly if safer.
                // Using relationships on retrieved models is safer than subqueries in select
                // when dealing with potential connection ambiguities.
                
                $unreadCount = Message::where('sender_id', $user->id)
                    ->where('receiver_id', $currentUserId)
                    ->whereNull('read_at')
                    ->count();
                    
                $latestMessage = Message::where(function ($q) use ($user, $currentUserId) {
                        $q->where('sender_id', $user->id)
                          ->where('receiver_id', $currentUserId);
                    })
                    ->orWhere(function ($q) use ($user, $currentUserId) {
                        $q->where('receiver_id', $user->id)
                          ->where('sender_id', $currentUserId);
                    })
                    ->latest()
                    ->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_online' => $user->is_online ?? false,
                    'last_seen' => $user->last_seen_at,
                    'unread_count' => $unreadCount,
                    'latest_message_at' => $latestMessage ? $latestMessage->created_at : null,
                ];
            })
            ->sortByDesc('latest_message_at')
            ->values(); // Reset keys

        return response()->json($users);
    }

    /**
     * Get conversation between current user and another user
     */
    public function getConversation(User $user)
    {
        $currentUserId = Auth::id();

        $messages = Message::where(function ($query) use ($currentUserId, $user) {
            $query->where('sender_id', $currentUserId)
                ->where('receiver_id', $user->id);
        })
            ->orWhere(function ($query) use ($currentUserId, $user) {
                $query->where('sender_id', $user->id)
                    ->where('receiver_id', $currentUserId);
            })
            ->with(['sender:id,name', 'receiver:id,name'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender_id' => $message->sender_id,
                    'receiver_id' => $message->receiver_id,
                    'sender_name' => $message->sender->name,
                    'receiver_name' => $message->receiver->name,
                    'read_at' => $message->read_at,
                    'created_at' => $message->created_at->toISOString(),
                ];
            });

        return response()->json($messages);
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id|different:' . Auth::id(),
            'content' => 'required|string|max:1000',
        ]);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'content' => trim($request->content),
        ]);

        $message->load(['sender:id,name', 'receiver:id,name']);

        // Broadcast the message
        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'content' => $message->content,
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'sender_name' => $message->sender->name,
                'receiver_name' => $message->receiver->name,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at->toISOString(),
            ]
        ]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(User $user)
    {
        $currentUserId = Auth::id();
        $currentUser = Auth::user();


        $updatedCount = Message::where('sender_id', $user->id)
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Broadcast read status to sender
        if ($updatedCount > 0) {
            broadcast(new MessageRead($currentUser, $user->id));
        }

        return response()->json([
            'success' => true,
            'marked_count' => $updatedCount
        ]);
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount()
    {
        $count = Message::where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Delete a message
     */
    public function deleteMessage(Message $message)
    {
        // Check if user owns the message
        if ($message->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get recent conversations
     */
    public function getRecentConversations()
    {
        $currentUserId = Auth::id();

        $conversations = DB::table('messages')
            ->select([
                DB::raw('CASE 
                    WHEN sender_id = ' . $currentUserId . ' THEN receiver_id 
                    ELSE sender_id 
                END as other_user_id'),
                DB::raw('MAX(created_at) as last_message_at'),
                DB::raw('COUNT(CASE WHEN receiver_id = ' . $currentUserId . ' AND read_at IS NULL THEN 1 END) as unread_count')
            ])
            ->where(function ($query) use ($currentUserId) {
                $query->where('sender_id', $currentUserId)
                    ->orWhere('receiver_id', $currentUserId);
            })
            ->groupBy('other_user_id')
            ->orderBy('last_message_at', 'desc')
            ->get();

        $userIds = $conversations->pluck('other_user_id');
        $users = User::whereIn('id', $userIds)
            ->select(['id', 'name', 'role', 'last_seen_at', 'is_online'])
            ->get()
            ->keyBy('id');

        $result = $conversations->map(function ($conversation) use ($users) {
            $user = $users->get($conversation->other_user_id);
            return [
                'user' => $user,
                'last_message_at' => $conversation->last_message_at,
                'unread_count' => $conversation->unread_count,
            ];
        });

        return response()->json($result);
    }

    public function markAsOffline()
    {
        $currentUser = Auth::user();

        if ($currentUser) {
            $currentUser->markOffline();
        }

        // return response()->json(['status' => 'offline']);
    }
}
