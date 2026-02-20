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
                    'latest_message_at' => $latestMessage ? $latestMessage->created_at->toISOString() : null, // Ensure ISO format
                ];
            })
            ->sortByDesc('latest_message_at')
            ->values(); // Reset keys

        return response()->json($users);
    }

    /**
     * Get conversation between current user and another user
     */
    public function getConversation(Request $request, $tenant, $userId)
    {
        // $tenant is the first route parameter (e.g., 'feedmill')
        // $userId is the second route parameter (e.g., '45')
        
        $currentUserId = Auth::id();
        
        Log::info('DEBUG: getConversation HIT', [
            'tenant' => $tenant,
            'auth_id' => $currentUserId,
            'requested_user_id' => $userId,
        ]);

        // Use strict connection for Message model to ensure we query 'mysql' DB
        $messages = Message::on('mysql')
            ->where(function ($query) use ($currentUserId, $userId) {
                $query->where('sender_id', $currentUserId)
                    ->where('receiver_id', $userId);
            })
            ->orWhere(function ($query) use ($currentUserId, $userId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $currentUserId);
            })
            ->orderBy('created_at', 'asc')
            ->get();
            
        Log::info('Messages found', ['count' => $messages->count()]);
        
        // Remove debug wrapper now that we know the issue
        /*
        $debugInfo = [
            'auth_id' => $currentUserId,
            'target_id' => $userId,
            'message_count' => $messages->count(),
            'query_log' => DB::getQueryLog(),
        ];
        */

        if ($messages->isEmpty()) {
             // Debug if messages exist at all in the main DB
             $countSender = Message::on('mysql')->where('sender_id', $currentUserId)->count();
             $countReceiver = Message::on('mysql')->where('receiver_id', $userId)->count();
             // Log::info("DEBUG MAIN DB: Sender($currentUserId) sent $countSender. Receiver($userId) received $countReceiver.");
        }

        // Transform collection and reset keys to ensure JSON array
        $messages = $messages->map(function ($message) {
                // Manually fetch names if needed
                // We must be careful here: User model might default to Tenant DB.
                // If users are synced, it's fine. If not, names might be missing.
                
                $senderName = 'Unknown';
                $receiverName = 'Unknown';
                
                try {
                     // Force user lookup on the connection where users exist (likely Tenant or Main depending on setup)
                     // Assuming users are in Tenant DB for now as per `getUsers` method
                     $sender = User::find($message->sender_id);
                     $receiver = User::find($message->receiver_id);
                     
                     if ($sender) $senderName = $sender->name;
                     if ($receiver) $receiverName = $receiver->name;
                } catch (\Exception $e) {
                    // Ignore relation errors
                }

                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'sender_id' => $message->sender_id,
                    'receiver_id' => $message->receiver_id,
                    'sender_name' => $senderName,
                    'receiver_name' => $receiverName,
                    'read_at' => $message->read_at,
                    'created_at' => $message->created_at->toISOString(),
                ];
            })->values();

        return response()->json($messages)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required',
            'content' => 'required|string',
        ]);

        $sender = Auth::user();
        $receiver = User::findOrFail($validated['receiver_id']);

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => $validated['content'],
        ]);

        // Load relationships for response
        $message->load(['sender:id,name', 'receiver:id,name']);

        // Format message for response/broadcast to match getConversation structure
        $formattedMessage = [
            'id' => $message->id,
            'content' => $message->content,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'sender_name' => $message->sender ? $message->sender->name : 'Unknown',
            'receiver_name' => $message->receiver ? $message->receiver->name : 'Unknown',
            'read_at' => null,
            'created_at' => $message->created_at->toISOString(),
        ];

        return response()->json(['message' => $formattedMessage]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, $user)
    {
        $user = User::findOrFail($user);
        $currentUserId = Auth::id();
        $currentUser = Auth::user();


        $updatedCount = Message::where('sender_id', $user->id)
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

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

        // Use the model to ensure tenant connection is used instead of default DB facade
        $conversations = Message::select([
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
            // Check if user exists (might be deleted or from another tenant context)
            $user = $users->get($conversation->other_user_id);
            if (!$user) return null;
            
            return [
                'user' => $user,
                'last_message_at' => $conversation->last_message_at,
                'unread_count' => $conversation->unread_count,
            ];
        })->filter()->values(); // Remove nulls and re-index

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
