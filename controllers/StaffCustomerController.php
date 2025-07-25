<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

use Models\Customer; // Make sure to use your Customer model

class StaffCustomerController extends Controller {

    /**
     * Displays the form to add a new customer.
     * Accessible via /staff/customers/add
     *
     * @return void
     */
    public function add() {
        Logger::log('CUSTOMER_ADD: Displaying new customer form.');
        $this->view('staff/customers/add', [
            'customer' => new Customer() // Pass an empty Customer model for form binding
        ],'staff');
    }

    /**
     * Handles the POST request to store a new customer in the database.
     * Accessible via /staff/customers/store
     *
     * @return void
     */
    public function store() {
        Logger::log('CUSTOMER_STORE: Attempting to store new customer.');

        // 1. Retrieve Input Data
        $customer_type             = $this->input('customer_type');
        $company_name              = $this->input('company_name');
        $contact_person_first_name = $this->input('contact_person_first_name');
        $contact_person_last_name  = $this->input('contact_person_last_name');
        $email                     = $this->input('email');
        $phone_number              = $this->input('phone_number');
        $address                   = $this->input('address');

        // 2. Validation
        $errors = [];

        if (empty($customer_type)) {
            $errors[] = 'Customer Type is required.';
        }
        if (empty($contact_person_first_name)) {
            $errors[] = 'Contact Person First Name is required.';
        }
        if (empty($contact_person_last_name)) {
            $errors[] = 'Contact Person Last Name is required.';
        }
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } else {
            // Check for unique email
            $existingCustomer = Customer::where('email', $email)->first();
            if ($existingCustomer) {
                $errors[] = 'Email already exists for another customer.';
            }
        }
        if (empty($phone_number)) {
            $errors[] = 'Phone Number is required.';
        }
        // Optional: Add more specific phone number validation (regex, length)

        if (!empty($errors)) {
            Logger::log("CUSTOMER_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            // Pass back input data to re-populate form fields
            $this->view('staff/customers/add', [
                'error' => implode('<br>', $errors),
                'customer' => (object)[ // Create a dummy object for form repopulation
                    'customer_type' => $customer_type,
                    'company_name' => $company_name,
                    'contact_person_first_name' => $contact_person_first_name,
                    'contact_person_last_name' => $contact_person_last_name,
                    'email' => $email,
                    'phone_number' => $phone_number,
                    'address' => $address,
                ]
            ],'staff');
            return;
        }

        // 3. Create and Save Customer
        try {
            $customer = new Customer();
            $customer->customer_type             = $customer_type;
            $customer->company_name              = !empty($company_name) ? $company_name : null;
            $customer->contact_person_first_name = $contact_person_first_name;
            $customer->contact_person_last_name  = $contact_person_last_name;
            $customer->email                     = $email;
            $customer->phone_number              = $phone_number;
            $customer->address                   = !empty($address) ? $address : null;

            $customer->save();

            Logger::log("CUSTOMER_STORE_SUCCESS: New customer '{$customer->contact_person_first_name} {$customer->contact_person_last_name}' (ID: {$customer->id}) added successfully.");
            header('Location: /staff/customers_list?success_message=' . urlencode('Customer added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("CUSTOMER_STORE_DB_ERROR: Failed to add customer - " . $e->getMessage());
            $this->view('staff/customers/add', [
                'error' => 'An error occurred while adding the customer. Please try again. ' . $e->getMessage(),
                'customer' => (object)[ // Re-populate form with submitted data
                    'customer_type' => $customer_type,
                    'company_name' => $company_name,
                    'contact_person_first_name' => $contact_person_first_name,
                    'contact_person_last_name' => $contact_person_last_name,
                    'email' => $email,
                    'phone_number' => $phone_number,
                    'address' => $address,
                ]
            ],'staff');
            return;
        }
    }

    /**
     * Displays the form to edit an existing customer.
     * Accessible via /staff/customers/edit/{id}
     *
     * @param int $id The ID of the customer to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("CUSTOMER_EDIT: Attempting to display edit form for customer ID: $id");

        $customer = Customer::find($id);

        if (!$customer) {
            Logger::log("CUSTOMER_EDIT_FAILED: Customer ID $id not found for editing.");
            return $this->view('errors/404', ['message' => 'Customer not found.'], 'staff');
        }

        Logger::log("CUSTOMER_EDIT_SUCCESS: Displaying edit form for customer ID: $id - {$customer->contact_person_first_name} {$customer->contact_person_last_name}");
        $this->view('staff/customers/edit', [
            'customer' => $customer
        ],'staff');
    }

    /**
     * Handles the POST request to update an existing customer in the database.
     * Accessible via /staff/customers/update
     *
     * @return void
     */
    public function update() {
        Logger::log('CUSTOMER_UPDATE: Attempting to update customer.');

        // 1. Retrieve Input Data
        $id                        = $this->input('id'); // Hidden field for customer ID
        $customer_type             = $this->input('customer_type');
        $company_name              = $this->input('company_name');
        $contact_person_first_name = $this->input('contact_person_first_name');
        $contact_person_last_name  = $this->input('contact_person_last_name');
        $email                     = $this->input('email');
        $phone_number              = $this->input('phone_number');
        $address                   = $this->input('address');

        // 2. Retrieve the Customer Model instance
        $customer = Customer::find($id);

        if (!$customer) {
            Logger::log("CUSTOMER_UPDATE_FAILED: Customer ID $id not found for update.");
            return $this->view('errors/404', ['message' => 'Customer not found.'], 'staff');
        }

        // 3. Validation
        $errors = [];

        if (empty($customer_type)) {
            $errors[] = 'Customer Type is required.';
        }
        if (empty($contact_person_first_name)) {
            $errors[] = 'Contact Person First Name is required.';
        }
        if (empty($contact_person_last_name)) {
            $errors[] = 'Contact Person Last Name is required.';
        }
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } else {
            // Check for unique email (excluding current customer)
            $existingCustomer = Customer::where('email', $email)->where('id', '!=', $id)->first();
            if ($existingCustomer) {
                $errors[] = 'Email already exists for another customer.';
            }
        }
        if (empty($phone_number)) {
            $errors[] = 'Phone Number is required.';
        }
        // Optional: Add more specific phone number validation (regex, length)

        if (!empty($errors)) {
            Logger::log("CUSTOMER_UPDATE_FAILED: Validation errors for Customer ID $id: " . implode(', ', $errors));
            $this->view('staff/customers/edit', [
                'error' => implode('<br>', $errors),
                'customer' => $customer, // Pass the original customer object back
            ],'staff');
            return;
        }

        // 4. Assign new values and check for changes
        $customer->customer_type             = $customer_type;
        $customer->company_name              = !empty($company_name) ? $company_name : null;
        $customer->contact_person_first_name = $contact_person_first_name;
        $customer->contact_person_last_name  = $contact_person_last_name;
        $customer->email                     = $email;
        $customer->phone_number              = $phone_number;
        $customer->address                   = !empty($address) ? $address : null;

        if (!$customer->isDirty()) {
            Logger::log("CUSTOMER_UPDATE_INFO: Customer ID $id submitted form with no changes.");
            $this->view('staff/customers/edit', [
                'success_message' => 'No changes were made to the customer.',
                'customer' => $customer,
            ],'staff');
            return;
        }

        // 5. Save Changes
        try {
            $customer->save();
            Logger::log("CUSTOMER_UPDATE_SUCCESS: Customer '{$customer->contact_person_first_name} {$customer->contact_person_last_name}' (ID: {$customer->id}) updated successfully.");
            header('Location: /staff/customers_list?success_message=' . urlencode('Customer updated successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("CUSTOMER_UPDATE_DB_ERROR: Failed to update customer ID $id - " . $e->getMessage());
            $this->view('staff/customers/edit', [
                'error' => 'An error occurred while updating the customer. Please try again. ' . $e->getMessage(),
                'customer' => $customer, // Pass the customer object back
            ],'staff');
            return;
        }
    }

    /**
     * Handles the deletion of a customer.
     * Accessible via /staff/customers/delete/{id}
     *
     * @param int $id The ID of the customer to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("CUSTOMER_DELETE: Attempting to delete customer ID: $id");

        $customer = Customer::find($id);

        if (!$customer) {
            Logger::log("CUSTOMER_DELETE_FAILED: Customer ID $id not found for deletion.");
            header('Location: /staff/customers_list?error=' . urlencode('Customer not found for deletion.'));
            exit();
        }

        try {
            // IMPORTANT: Foreign Key Constraints Check!
            // If you have transactions or other data linked to this customer, deleting the customer
            // will cause a foreign key constraint violation error unless:
            // 1. Your database's foreign key is set to ON DELETE CASCADE (might not be desired for customer history).
            // 2. You manually dissociate or delete related records first (e.g., set customer_id to null in transactions).
            // 3. You prevent deletion if related records exist.
            //    Example of preventing deletion if transactions exist (assuming 'transactions' relationship in Customer model):
            //    if ($customer->transactions()->count() > 0) { // requires 'transactions' relationship in Customer model
            //        Logger::log("CUSTOMER_DELETE_FAILED: Customer ID $id has associated transactions and cannot be deleted.");
            //        header('Location: /staff/customers_list?error=' . urlencode('Cannot delete customer because they have associated transactions. Please reassign or delete transactions first.'));
            //        exit();
            //    }

            $customer->delete();
            Logger::log("CUSTOMER_DELETE_SUCCESS: Customer '{$customer->contact_person_first_name} {$customer->contact_person_last_name}' (ID: {$customer->id}) deleted successfully.");
            header('Location: /staff/customers_list?success_message=' . urlencode('Customer deleted successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("CUSTOMER_DELETE_DB_ERROR: Failed to delete customer ID $id - " . $e->getMessage());
            header('Location: /staff/customers_list?error=' . urlencode('An error occurred while deleting the customer: ' . $e->getMessage()));
            exit();
        }
    }
}