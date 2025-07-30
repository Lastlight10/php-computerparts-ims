<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Models\Brand;    // Make sure this path is correct for your Brand model
use Models\Products;
require_once 'vendor/autoload.php';
require_once 'core/Connection.php'; // Assuming this sets up Eloquent Capsule

class StaffBrandController extends Controller {
    // Assuming view() and input() are inherited from base Controller

    /**
     * Displays the form to add a new brand.
     * Accessible via /staff/brands/add
     *
     * @return void
     */
    public function add() {
        Logger::log('BRAND_ADD: Displaying new brand form.');
        $this->view('staff/brands/add', [
            'brand' => new Brand() // Pass an empty Brand model for form binding
        ],'staff');
    }

    /**
     * Handles the POST request to store a new brand in the database.
     * Accessible via /staff/brands/store
     *
     * @return void
     */
    public function store() {
        Logger::log('BRAND_STORE: Attempting to store new brand.');

        // 1. Retrieve Input Data
        $name = trim($this->input('name'));
        $website = trim($this->input('website'));         // NEW: Retrieve website
        $contact_email = trim($this->input('contact_email')); // NEW: Retrieve contact_email

        // 2. Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Brand Name is required.';
        } else {
            $existingBrand = Brand::where('name', $name)->first();
            if ($existingBrand) {
                $errors[] = 'Brand Name must be unique.';
            }
        }

        // NEW: Basic Validation for website and contact_email (optional but good practice)
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Website must be a valid URL.';
        }
        if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact Email must be a valid email address.';
        }

        if (!empty($errors)) {
            Logger::log("BRAND_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            // Pass back input data to re-populate form fields
            $_SESSION['error_message']="ERRORS: ". implode("<br>", $errors);
            $this->view('staff/brands/add', [
                'brand' => (object)['name' => $name, 'website' => $website, 'contact_email' => $contact_email]
            ],'staff');
            return;
        }

        // 3. Create and Save Brand
        try {
            $brand = new Brand();
            $brand->name = $name;
            $brand->website = $website;             // NEW: Assign website
            $brand->contact_email = $contact_email; // NEW: Assign contact_email

            $brand->save();

            Logger::log("BRAND_STORE_SUCCESS: New brand '{$brand->name}' (ID: {$brand->id}) added successfully.");
            $_SESSION['success_message']="New brand " . $brand->name ." has been added.";
            header('Location: /staff/brands_list');
            exit();

        } catch (\Exception $e) {
            Logger::log("BRAND_STORE_DB_ERROR: Failed to add brand - " . $e->getMessage());
            $_SESSION['error_message']= 'Failed to add a new brand. See: '. $e->getMessage();
            $this->view('staff/brands/add', [
                'brand' => (object)['name' => $name, 'website' => $website, 'contact_email' => $contact_email]
            ],'staff');
            return;
        }
    }

    /**
     * Displays the form to edit an existing brand.
     * Accessible via /staff/brands/edit/{id}
     *
     * @param int $id The ID of the brand to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("BRAND_EDIT: Attempting to display edit form for brand ID: $id");

        $brand = Brand::find($id);

        if (!$brand) {
            Logger::log("BRAND_EDIT_FAILED: Brand ID $id not found for editing.");
            $_SESSION['error_message']="Brand not found.";
            return $this->view('staff/brands_list', ['message' => 'Brand not found.'],'staff'); // Ensure staff layout here too
        }

        Logger::log("BRAND_EDIT_SUCCESS: Displaying edit form for brand ID: $id - {$brand->name}");
        $this->view('staff/brands/edit', [
            'brand' => $brand
        ],'staff');
    }

    /**
     * Handles the POST request to update an existing brand in the database.
     * Accessible via /staff/brands/update
     *
     * @return void
     */
    public function update() {
        Logger::log('BRAND_UPDATE: Attempting to update brand.');

        // 1. Retrieve Input Data
        $id = trim($this->input('id')); // Hidden field for brand ID
        $name = trim($this->input('name'));
        $website = trim($this->input('website'));         // NEW: Retrieve website
        $contact_email = trim($this->input('contact_email')); // NEW: Retrieve contact_email

        // 2. Retrieve the Brand Model instance
        $brand = Brand::find($id);

        if (!$brand) {
            Logger::log("BRAND_UPDATE_FAILED: Brand ID $id not found for update.");
            $_SESSION['error_message']="Brand not found.";
            return $this->view('staff/brands_list', ['message' => 'Brand not found.'],'staff');
        }

        // 3. Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Brand Name is required.';
        } else {
            // Check if Brand Name already exists for another brand (excluding current brand)
            $existingBrand = Brand::where('name', $name)->where('id', '!=', $id)->first();
            if ($existingBrand) {
                $errors[] = 'Brand Name already taken by another brand.';
            }
        }

        // NEW: Basic Validation for website and contact_email (optional but good practice)
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Website must be a valid URL.';
        }
        if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact Email must be a valid email address.';
        }

        if (!empty($errors)) {
            Logger::log("BRAND_UPDATE_FAILED: Validation errors for Brand ID $id: " . implode(', ', $errors));
            $_SESSION['error_message']="Error: ". implode('<br>', $errors);
            $this->view('staff/brands/edit', [
                'brand' => $brand, // Pass the original brand object back (it already has correct website/email if from DB)
            ],'staff');
            return;
        }

        // 4. Assign new values and check for changes
        $brand->name = $name;
        $brand->website = $website;             // NEW: Assign website
        $brand->contact_email = $contact_email; // NEW: Assign contact_email

        if (!$brand->isDirty()) {
            Logger::log("BRAND_UPDATE_INFO: Brand ID $id submitted form with no changes.");
            $_SESSION['warning_message']="No changes were made on " . $brand->name . ".";
            $this->view('staff/brands/edit', [
                'brand' => $brand,
            ],'staff');
            return;
        }

        // 5. Save Changes
        try {
            $brand->save();
            Logger::log("BRAND_UPDATE_SUCCESS: Brand '{$brand->name}' (ID: {$brand->id}) updated successfully.");
            $_SESSION['success_message']="Updated " . $brand->name . " successfully.";
            header('Location: /staff/brands_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("BRAND_UPDATE_DB_ERROR: Failed to update brand ID $id - " . $e->getMessage());

            $_SESSION['error_message']= "Failed to updated brand ". $brand->name . ".";

            $this->view('staff/brands/edit', [
                'brand' => $brand, // Pass the brand object back
            ],'staff');
            return;
        }
    }

    /**
     * Handles the deletion of a brand.
     * Accessible via /staff/brands/delete/{id}
     *
     * @param int $id The ID of the brand to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("BRAND_DELETE: Attempting to delete brand ID: $id");

        $brand = Brand::find($id);

        if (!$brand) {
            Logger::log("BRAND_DELETE_FAILED: Brand ID $id not found for deletion.");
            $_SESSION['error_message']="Brand " . $brand->name . " not found.";
            header('Location: /staff/brands_list');
            exit();
        }

        try {

            $brand->delete();
            Logger::log("BRAND_DELETE_SUCCESS: Brand '{$brand->name}' (ID: {$brand->id}) deleted successfully.");
            $_SESSION['success_message']= "Successfuly deleted ". $brand->name . ".";
            header('Location: /staff/brands_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("BRAND_DELETE_DB_ERROR: Failed to delete brand ID $id - " . $e->getMessage());
            $_SESSION['error_message']="Failed to deleted " . $brand->name . ".";
            header('Location: /staff/brands_list');
            exit();
        }
    }
}