<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

use Models\Supplier;

class StaffSupplierController extends Controller {

    /**
     * Displays the form to add a new supplier.
     * Accessible via /staff/suppliers/add
     *
     * @return void
     */
    public function add() {
        Logger::log('SUPPLIER_ADD: Displaying new supplier form.');
        $this->view('staff/suppliers/add', [
            'supplier' => new Supplier()
        ],'staff');
    }

    /**
     * Handles the POST request to store a new supplier in the database.
     * Accessible via /staff/suppliers/store
     *
     * @return void
     */
    public function store() {
        Logger::log('SUPPLIER_STORE: Attempting to store new supplier.');

        // 1. Retrieve Input Data
        $supplier_type             = trim($this->input('supplier_type'));
        $company_name              = trim($this->input('company_name'));
        $contact_first_name        = trim($this->input('contact_first_name'));
        $contact_middle_name       = trim($this->input('contact_middle_name')); // ADDED: Retrieve middle name
        $contact_last_name         = trim($this->input('contact_last_name'));
        $email                     = trim($this->input('email'));
        $phone_number              = trim($this->input('phone_number'));
        $address                   = trim($this->input('address'));

        // 2. Validation
        $errors = [];

        if (empty($supplier_type)) {
            $errors[] = 'Supplier Type is required.';
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
            $existingSupplier = Supplier::where('email', $email)->first();
            if ($existingSupplier) {
                $errors[] = 'Email already exists for another supplier.';
            }
        }
        if (empty($phone_number)) {
            $errors[] = 'Phone Number is required.';
        }

        if (!empty($errors)) {
            Logger::log("SUPPLIER_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            $this->view('staff/suppliers/add', [
                'error' => implode('<br>', $errors),
                'supplier' => (object)[
                    'supplier_type' => $supplier_type,
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

        // 3. Create and Save Supplier
        try {
            $supplier = new Supplier();
            $supplier->supplier_type             = $supplier_type;
            $supplier->company_name              = !empty($company_name) ? $company_name : null;
            $supplier->contact_first_name        = $contact_first_name;
            $supplier->contact_middle_name       = !empty($contact_middle_name) ? $contact_middle_name : null; // ADDED: Assign middle name
            $supplier->contact_last_name         = $contact_last_name;
            $supplier->email                     = $email;
            $supplier->phone_number              = $phone_number;
            $supplier->address                   = !empty($address) ? $address : null;

            $supplier->save();

            Logger::log("SUPPLIER_STORE_SUCCESS: New supplier '{$supplier->contact_first_name} {$supplier->contact_last_name}' (ID: {$supplier->id}) added successfully.");
            header('Location: /staff/suppliers_list?success_message=' . urlencode('Supplier added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("SUPPLIER_STORE_DB_ERROR: Failed to add supplier - " . $e->getMessage());
            $this->view('staff/suppliers/add', [
                'error' => 'An error occurred while adding the supplier. Please try again. ' . $e->getMessage(),
                'supplier' => (object)[
                    'supplier_type' => $supplier_type,
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
     * Displays the form to edit an existing supplier.
     * Accessible via /staff/suppliers/edit/{id}
     *
     * @param int $id The ID of the supplier to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("SUPPLIER_EDIT: Attempting to display edit form for supplier ID: $id");

        $supplier = Supplier::find($id);

        if (!$supplier) {
            Logger::log("SUPPLIER_EDIT_FAILED: Supplier ID $id not found for editing.");
            return $this->view('errors/404', ['message' => 'Supplier not found.'], 'staff');
        }

        Logger::log("SUPPLIER_EDIT_SUCCESS: Displaying edit form for supplier ID: $id - {$supplier->contact_first_name} {$supplier->contact_last_name}");
        $this->view('staff/suppliers/edit', [
            'supplier' => $supplier
        ],'staff');
    }

    /**
     * Handles the POST request to update an existing supplier in the database.
     * Accessible via /staff/suppliers/update
     *
     * @return void
     */
    public function update() {
        Logger::log('SUPPLIER_UPDATE: Attempting to update supplier.');

        // 1. Retrieve Input Data
        $id                        = trim($this->input('id'));
        $supplier_type             = trim($this->input('supplier_type'));
        $company_name              = trim($this->input('company_name'));
        $contact_first_name        = trim($this->input('contact_first_name'));
        $contact_middle_name       = trim($this->input('contact_middle_name')); // ADDED: Retrieve middle name
        $contact_last_name         = trim($this->input('contact_last_name'));
        $email                     = trim($this->input('email'));
        $phone_number              = trim($this->input('phone_number'));
        $address                   = trim($this->input('address'));

        // 2. Retrieve the Supplier Model instance
        $supplier = Supplier::find($id);

        if (!$supplier) {
            Logger::log("SUPPLIER_UPDATE_FAILED: Supplier ID $id not found for update.");
            return $this->view('errors/404', ['message' => 'Supplier not found.'], 'staff');
        }

        // 3. Validation
        $errors = [];

        if (empty($supplier_type)) {
            $errors[] = 'Supplier Type is required.';
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
            $existingSupplier = Supplier::where('email', $email)->where('id', '!=', $id)->first();
            if ($existingSupplier) {
                $errors[] = 'Email already exists for another supplier.';
            }
        }
        if (empty($phone_number)) {
            $errors[] = 'Phone Number is required.';
        }

        if (!empty($errors)) {
            Logger::log("SUPPLIER_UPDATE_FAILED: Validation errors for Supplier ID $id: " . implode(', ', $errors));
            $supplier->supplier_type = $supplier_type;
            $supplier->company_name = $company_name;
            $supplier->contact_first_name = $contact_first_name;
            $supplier->contact_middle_name = $contact_middle_name; // ADDED: For repopulation
            $supplier->contact_last_name = $contact_last_name;
            $supplier->email = $email;
            $supplier->phone_number = $phone_number;
            $supplier->address = $address;

            $this->view('staff/suppliers/edit', [
                'error' => implode('<br>', $errors),
                'supplier' => $supplier,
            ],'staff');
            return;
        }

        // 4. Assign new values and check for changes
        $supplier->supplier_type             = $supplier_type;
        $supplier->company_name              = !empty($company_name) ? $company_name : null;
        $supplier->contact_first_name        = $contact_first_name;
        $supplier->contact_middle_name       = !empty($contact_middle_name) ? $contact_middle_name : null; // ADDED: Assign middle name
        $supplier->contact_last_name         = $contact_last_name;
        $supplier->email                     = $email;
        $supplier->phone_number              = $phone_number;
        $supplier->address                   = !empty($address) ? $address : null;

        if (!$supplier->isDirty()) {
            Logger::log("SUPPLIER_UPDATE_INFO: Supplier ID $id submitted form with no changes.");
            $this->view('staff/suppliers/edit', [
                'success_message' => 'No changes were made to the supplier.',
                'supplier' => $supplier,
            ],'staff');
            return;
        }

        // 5. Save Changes
        try {
            $supplier->save();
            Logger::log("SUPPLIER_UPDATE_SUCCESS: Supplier '{$supplier->contact_first_name} {$supplier->contact_last_name}' (ID: {$supplier->id}) updated successfully.");
            header('Location: /staff/suppliers_list?success_message=' . urlencode('Supplier updated successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("SUPPLIER_UPDATE_DB_ERROR: Failed to update supplier ID $id - " . $e->getMessage());
            $this->view('staff/suppliers/edit', [
                'error' => 'An error occurred while updating the supplier. Please try again. ' . $e->getMessage(),
                'supplier' => $supplier,
            ],'staff');
            return;
        }
    }

    /**
     * Displays the list of all suppliers.
     * Accessible via /staff/suppliers_list
     *
     * @return void
     */
    public function suppliers_list() {
        Logger::log('Reached List of Suppliers');

        $suppliers_info = Supplier::select(
            'id',
            'supplier_type',
            'company_name',
            'contact_first_name',
            'contact_middle_name',
            'contact_last_name',
            'email',
            'phone_number',
            'address',
            'created_at',
            'updated_at'
        )->get();

        $this->view('staff/suppliers_list', [
            'suppliers_info' => $suppliers_info
        ], 'staff');
    }

    /**
     * Handles the deletion of a supplier.
     * Accessible via /staff/suppliers/delete/{id}
     *
     * @param int $id The ID of the supplier to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("SUPPLIER_DELETE: Attempting to delete supplier ID: $id");

        $supplier = Supplier::find($id);

        if (!$supplier) {
            Logger::log("SUPPLIER_DELETE_FAILED: Supplier ID $id not found for deletion.");
            header('Location: /staff/suppliers_list?error=' . urlencode('Supplier not found for deletion.'));
            exit();
        }

        try {
            $supplier->delete();
            Logger::log("SUPPLIER_DELETE_SUCCESS: Supplier '{$supplier->contact_first_name} {$supplier->contact_last_name}' (ID: {$supplier->id}) deleted successfully.");
            header('Location: /staff/suppliers_list?success_message=' . urlencode('Supplier deleted successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("SUPPLIER_DELETE_DB_ERROR: Failed to delete supplier ID $id - " . $e->getMessage());
            header('Location: /staff/suppliers_list?error=' . urlencode('An error occurred while deleting the supplier: ' . $e->getMessage()));
            exit();
        }
    }
}