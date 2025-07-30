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
        $customer_type             = trim($this->input('customer_type'));
        $company_name              = trim($this->input('company_name'));
        $contact_first_name        = trim($this->input('contact_first_name')); // Corrected name
        $contact_middle_name       = trim($this->input('contact_middle_name')); // ADDED: Retrieve middle name
        $contact_last_name         = trim($this->input('contact_last_name'));  // Corrected name
        $email                     = trim($this->input('email'));
        $phone_number              = trim($this->input('phone_number'));
        $address                   = trim($this->input('address'));

        // 2. Validation
        $errors = [];

        if (empty($customer_type)) {
            $errors[] = 'Customer Type is required.';
        }
        if (empty($contact_first_name)) {
            $errors[] = 'Contact Person First Name is required.';
        }
        // Middle name is optional, so no 'required' validation for it here
        if (empty($contact_last_name)) {
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

        if (!empty($errors)) {
            Logger::log("CUSTOMER_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            
            $_SESSION['error_message'] = "Failed to add Customer: " . implode('<br>', $errors);
            $this->view('staff/customers/add', [
                'customer' => (object)[ // Create a dummy object for form repopulation
                    'customer_type' => $customer_type,
                    'company_name' => $company_name,
                    'contact_first_name' => $contact_first_name,
                    'contact_middle_name' => $contact_middle_name, // ADDED: For repopulation
                    'contact_last_name' => $contact_last_name,
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
            $customer->contact_first_name        = $contact_first_name;
            $customer->contact_middle_name       = !empty($contact_middle_name) ? $contact_middle_name : null; // ADDED: Assign middle name
            $customer->contact_last_name         = $contact_last_name;
            $customer->email                     = $email;
            $customer->phone_number              = $phone_number;
            $customer->address                   = !empty($address) ? $address : null;

            $customer->save();

            Logger::log("CUSTOMER_STORE_SUCCESS: New customer '{$customer->contact_first_name} {$customer->contact_last_name}' (ID: {$customer->id}) added successfully.");

            $_SESSION['success_message'] = "Successfully added customer " . $customer->company_name;
            header('Location: /staff/customers_list?');
            exit();

        } catch (\Exception $e) {
            Logger::log("CUSTOMER_STORE_DB_ERROR: Failed to add customer - " . $e->getMessage());

            $_SESSION['error_message'] = "Failed to add customer ". $e->getMessage();
            $this->view('staff/customers/add', [
                'customer' => (object)[ // Re-populate form with submitted data
                    'customer_type' => $customer_type,
                    'company_name' => $company_name,
                    'contact_first_name' => $contact_first_name,
                    'contact_middle_name' => $contact_middle_name, // ADDED: For repopulation
                    'contact_last_name' => $contact_last_name,
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

            $_SESSION['error_message']="Customer not found.";
            return $this->view('staff/customers/edit', ['message' => 'Customer not found.'], 'staff');
        }

        Logger::log("CUSTOMER_EDIT_SUCCESS: Displaying edit form for customer ID: $id - {$customer->contact_first_name} {$customer->contact_last_name}");
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
        $id                        = trim($this->input('id'));
        $customer_type             = trim($this->input('customer_type'));
        $company_name              = trim($this->input('company_name'));
        $contact_first_name        = trim($this->input('contact_first_name')); // Corrected name
        $contact_middle_name       = trim($this->input('contact_middle_name')); // ADDED: Retrieve middle name
        $contact_last_name         = trim($this->input('contact_last_name'));  // Corrected name
        $email                     = trim($this->input('email'));
        $phone_number              = trim($this->input('phone_number'));
        $address                   = trim($this->input('address'));

        // 2. Retrieve the Customer Model instance
        $customer = Customer::find($id);

        if (!$customer) {
            Logger::log("CUSTOMER_UPDATE_FAILED: Customer ID $id not found for update.");

            $_SESSION['error_message']="Customer not found.";
            return $this->view('staff/customer/edit', ['message' => 'Customer not found.'], 'staff');
        }

        // 3. Validation
        $errors = [];

        if (empty($customer_type)) {
            $errors[] = 'Customer Type is required.';
        }
        if (empty($contact_first_name)) {
            $errors[] = 'Contact Person First Name is required.';
        }
        // Middle name is optional, so no 'required' validation for it here
        if (empty($contact_last_name)) {
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

        if (!empty($errors)) {
            Logger::log("CUSTOMER_UPDATE_FAILED: Validation errors for Customer ID $id: " . implode(', ', $errors));
            // Pass the original customer object back, but update with submitted values for repopulation
            $customer->customer_type = $customer_type;
            $customer->company_name = $company_name;
            $customer->contact_first_name = $contact_first_name;
            $customer->contact_middle_name = $contact_middle_name; // ADDED: For repopulation
            $customer->contact_last_name = $contact_last_name;
            $customer->email = $email;
            $customer->phone_number = $phone_number;
            $customer->address = $address;

            $_SESSION['error_message']= "Can't update customer: " . implode('<br>', $errors);
   
            $this->view('staff/customers/edit', [
                'customer' => $customer,
            ],'staff');
            return;
        }

        // 4. Assign new values and check for changes
        $customer->customer_type             = $customer_type;
        $customer->company_name              = !empty($company_name) ? $company_name : null;
        $customer->contact_first_name        = $contact_first_name;
        $customer->contact_middle_name       = !empty($contact_middle_name) ? $contact_middle_name : null; // ADDED: Assign middle name
        $customer->contact_last_name         = $contact_last_name;
        $customer->email                     = $email;
        $customer->phone_number              = $phone_number;
        $customer->address                   = !empty($address) ? $address : null;

        if (!$customer->isDirty()) {
            Logger::log("CUSTOMER_UPDATE_INFO: Customer ID $id submitted form with no changes.");
            $_SESSION['warning_message']= "No Changes made.";
            $this->view('staff/customers/edit', [
                'customer' => $customer,
            ],'staff');
            return;
        }

        // 5. Save Changes
        try {
            $customer->save();
            Logger::log("CUSTOMER_UPDATE_SUCCESS: Customer '{$customer->contact_first_name} {$customer->contact_last_name}' (ID: {$customer->id}) updated successfully.");

            $_SESSION['success_message']="Successfully updated customer ". $customer->company_name;
            header('Location: /staff/customers_list?');
            exit();
        } catch (\Exception $e) {
            Logger::log("CUSTOMER_UPDATE_DB_ERROR: Failed to update customer ID $id - " . $e->getMessage());

            $_SESSION['error_message']="Failed to update customer.";
            $this->view('staff/customers/edit', [
                'customer' => $customer,
            ],'staff');
            return;
        }
    }

    /**
     * Displays the list of all customers.
     * Accessible via /staff/customers_list
     *
     * @return void
     */
    public function customers_list() {
        Logger::log('Reached List of Customers');
        $customers_info = Customer::select(
            'id',
            'customer_type',
            'company_name',
            'contact_first_name',
            'contact_middle_name', // ADDED: select middle name
            'contact_last_name',
            'email',
            'phone_number',
            'address',
            'created_at', // ADDED: select created_at
            'updated_at'  // ADDED: select updated_at
        )->get();
        $this->view('staff/customers_list', ['customers_info' => $customers_info],'staff');
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

            $_SESSION['error_message']="Customer not found.";
            header('Location: /staff/customers_list');
            exit();
        }

        try {
            $customer->delete();
            Logger::log("CUSTOMER_DELETE_SUCCESS: Customer '{$customer->contact_first_name} {$customer->contact_last_name}' (ID: {$customer->id}) deleted successfully.");

            $_SESSION['success_message']="Customer successfully deleted.";
            header('Location: /staff/customers_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("CUSTOMER_DELETE_DB_ERROR: Failed to delete customer ID $id - " . $e->getMessage());

            $_SESSION['error_message']="Failed to delete customer";
            header('Location: /staff/customers_list');
            exit();
        }
    }
}