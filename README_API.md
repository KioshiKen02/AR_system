# API Documentation

This API supports multi-tenant data access using a `bu_id` (Business Unit ID) parameter. You can access specific tenant databases by appending `?bu_id={id}` to your API requests.

## Business Unit IDs (`bu_id`)

Use the following IDs to access the corresponding Business Unit:

| Business Unit Name | `bu_id` |
| :--- | :--- |
| **Cortes Piggery** | `11` |
| **Cortes Poultry** | `12` |
| **Bilar Breeder** | `13` |
| **Bilar Hatchery** | `14` |
| **Canhayupon Breeder** | `15` |
| **Lapsaon Breeder** | `16` |
| **Ice Plant** | `25` |
| **Peanut Kisses** | `26` |
| **Rizal Breeder** | `43` |
| **Gp Jagna** | `50` |

# UBAY SERVER
| **Dressing Plant** | `17` |
| **Rendering** | `18` |
| **Feedmill** | `19` |
| **Growout** | `20` |
| **Demo Farm** | `21` |
| **Ubay Fertilizer** | `22` |
| **Piggery Untaga** | `23` |
| **Farmers Market** | `41` |
| **Cortes Fertilizer** | `42` |
| **Meat Processing** | `46` |


---

## Available Endpoints

All endpoints are prefixed with `/api`.

### 1. Get Cash In Bank List
Retrieves the list of cash in bank accounts.

*   **URL:** `/api/cash-in-bank`
*   **Method:** `GET`
*   **Parameter:** `bu_id` (Required)
*   **Example:**
    ```
    GET /api/cash-in-bank?bu_id=19
    ```

### 2. Get Latest Payment Number
Retrieves the next available payment number.

*   **URL:** `/api/getlatestpaymentno`
*   **Method:** `GET`
*   **Parameter:** `bu_id` (Required)
*   **Example:**
    ```
    GET /api/getlatestpaymentno?bu_id=19
    ```

### 3. Insert Customer Ledger
Creates a new customer ledger entry.

*   **URL:** `/api/insertcustomerledger`
*   **Method:** `POST`
*   **Parameter:** `bu_id` (Required)
*   **Body:** (JSON)
    ```json
    {
        "invoice_number": "INV-001",
        "date": "2023-10-27",
        "type": "Sales Invoice",
        "customer_code": "CUST001",
        ...
    }
    ```
*   **Example:**
    ```
    POST /api/insertcustomerledger?bu_id=19
    ```

### 4. Update Customer Ledger
Updates an existing customer ledger entry (shrinkage, overage, return).

*   **URL:** `/api/updatecustomerledger`
*   **Method:** `PUT`
*   **Parameter:** `bu_id` (Required)
*   **Body:** (JSON)
    ```json
    {
        "invoice_number": "INV-001",
        "type": "Sales Invoice",
        "shrinkage": 0,
        "overage": 0,
        "return": 0
    }
    ```
*   **Example:**
    ```
    PUT /api/updatecustomerledger?bu_id=19
    ```

### 5. Insert Payment
Records a new payment transaction.

*   **URL:** `/api/insertpayment`
*   **Method:** `POST`
*   **Parameter:** `bu_id` (Required)
*   **Body:** (JSON)
    ```json
    {
        "payment_no": "PAY-001",
        "receipt_date": "2023-10-27",
        "transaction_date": "2023-10-27",
        "customer_code": "CUST001",
        "name": "Customer Name",
        "payment_type": "5A - Cash",
        "type": "Sales Invoice",
        "document_no": "INV-001",
        "document_date": "2023-10-27",
        "advpy_amount_paid": 0,
        "total_amount": "1000.00",
        "amount_paid": 1000.00,
        "wht_amount": 50.00,
        "created_by": "Surname, First Name"
    }
    ```
*   **Example:**
    ```
    POST /api/insertpayment?bu_id=19
    ```

### 6. Get Customer Ledger List
Checks the status of a specific document (e.g., if it is Paid).

*   **URL:** `/api/get-customerledger-list`
*   **Method:** `GET`
*   **Parameters:**
    *   `bu_id` (Required)
    *   `document_no` (Required) - The Invoice Number
*   **Example:**
    ```
    GET /api/get-customerledger-list?bu_id=19&document_no=INV-001
    ```
