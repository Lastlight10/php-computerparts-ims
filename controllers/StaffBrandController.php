<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Models\Brand;    // Make sure this path is correct for your Brand model

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
        $name = $this->input('name');
        $website = $this->input('website');         // NEW: Retrieve website
        $contact_email = $this->input('contact_email'); // NEW: Retrieve contact_email

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
            $this->view('staff/brands/add', [
                'error' => implode('<br>', $errors),
                // NEW: Include website and contact_email in the dummy object for repopulation
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
            // Redirect to brand list
            header('Location: /staff/brands_list?success_message=' . urlencode('Brand added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("BRAND_STORE_DB_ERROR: Failed to add brand - " . $e->getMessage());
            $this->view('staff/brands/add', [
                'error' => 'An error occurred while adding the brand. Please try again. ' . $e->getMessage(),
                // NEW: Re-populate form with submitted data
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
            return $this->view('errors/404', ['message' => 'Brand not found.'],'staff'); // Ensure staff layout here too
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
        $id = $this->input('id'); // Hidden field for brand ID
        $name = $this->input('name');
        $website = $this->input('website');         // NEW: Retrieve website
        $contact_email = $this->input('contact_email'); // NEW: Retrieve contact_email

        // 2. Retrieve the Brand Model instance
        $brand = Brand::find($id);

        if (!$brand) {
            Logger::log("BRAND_UPDATE_FAILED: Brand ID $id not found for update.");
            return $this->view('errors/404', ['message' => 'Brand not found.'],'staff');
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
            $this->view('staff/brands/edit', [
                'error' => implode('<br>', $errors),
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
            $this->view('staff/brands/edit', [
                'success_message' => 'No changes were made to the brand.',
                'brand' => $brand,
            ],'staff');
            return;
        }

        // 5. Save Changes
        try {
            $brand->save();
            Logger::log("BRAND_UPDATE_SUCCESS: Brand '{$brand->name}' (ID: {$brand->id}) updated successfully.");
            header('Location: /staff/brands_list?success_message=' . urlencode('Brand updated successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("BRAND_UPDATE_DB_ERROR: Failed to update brand ID $id - " . $e->getMessage());
            $this->view('staff/brands/edit', [
                'error' => 'An error occurred while updating the brand. Please try again. ' . $e->getMessage(),
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
            header('Location: /staff/brands_list?error=' . urlencode('Brand not found for deletion.'));
            exit();
        }

        try {
            // IMPORTANT: Foreign Key Constraints Check! (Keep this important comment for future reference)
            // If you have products linked to this brand, deleting the brand
            // will cause a foreign key constraint violation error unless:
            // 1. Your database's foreign key is set to ON DELETE CASCADE (unlikely desired).
            // 2. You manually dissociate or delete related products first (e.g., Product::where('brand_id', $id)->update(['brand_id' => null]);).
            // 3. You prevent deletion if related products exist.
            //    Example of preventing deletion if products exist (assuming 'products' relationship in Brand model):
            //    if ($brand->products()->count() > 0) {
            //        Logger::log("BRAND_DELETE_FAILED: Brand ID $id has associated products and cannot be deleted.");
            //        header('Location: /staff/brands_list?error=' . urlencode('Cannot delete brand because it has associated products. Please reassign products first.'));
            //        exit();
            //    }

            $brand->delete();
            Logger::log("BRAND_DELETE_SUCCESS: Brand '{$brand->name}' (ID: {$brand->id}) deleted successfully.");
            header('Location: /staff/brands_list?success_message=' . urlencode('Brand deleted successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("BRAND_DELETE_DB_ERROR: Failed to delete brand ID $id - " . $e->getMessage());
            header('Location: /staff/brands_list?error=' . urlencode('An error occurred while deleting the brand: ' . $e->getMessage()));
            exit();
        }
    }
}