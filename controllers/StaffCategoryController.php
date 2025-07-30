<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Models\Category;
use Models\Products;
require_once 'vendor/autoload.php';

class StaffCategoryController extends Controller {
    /**
     * Displays the form to add a new category.
     * Accessible via /staff/categories/add
     *
     * @return void
     */
    public function add() {
        Logger::log('CATEGORY_ADD: Displaying new category form.');
        $this->view('staff/categories/add', [
            'category' => new Category() // Pass an empty Category model for form binding
        ],'staff');
    }

    /**
     * Handles the POST request to store a new category in the database.
     * Accessible via /staff/categories/store
     *
     * @return void
     */
    public function store() {
        Logger::log('CATEGORY_STORE: Attempting to store new category.');

        // 1. Retrieve Input Data
        $name = trim($this->input('name'));
        $description = trim($this->input('description')); // NEW: Retrieve description

        // 2. Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Category Name is required.';
        } else {
            $existingCategory = Category::where('name', $name)->first();
            if ($existingCategory) {
                $errors[] = 'Category Name must be unique.';
            }
        }

        // Optional: Add validation for description if needed (e.g., max length)
        // if (!empty($description) && strlen($description) > 500) {
        //     $errors[] = 'Description cannot exceed 500 characters.';
        // }


        if (!empty($errors)) {
            Logger::log("CATEGORY_STORE_FAILED: Validation errors: " . implode(', ', $errors));
            $_SESSION['error_message']="Error: ".implode("<br>", $errors);
            // Pass back input data to re-populate form fields
            $this->view('staff/categories/add', [
                // NEW: Include description in the dummy object for repopulation
                'category' => (object)['name' => $name, 'description' => $description]
            ],'staff');
            return;
        }

        // 3. Create and Save Category
        try {
            $category = new Category();
            $category->name = $name;
            $category->description = $description; // NEW: Assign description
            // Add any other category-specific fields here if your model has them

            $category->save();

            Logger::log("CATEGORY_STORE_SUCCESS: New category '{$category->name}' (ID: {$category->id}) added successfully.");

            $_SESSION['success_message']="New category ".$category->name . " added.";
            // Redirect to category list, which you indicated is handled by StaffController
            header('Location: /staff/categories_list');
            exit();

        } catch (\Exception $e) {
            Logger::log("CATEGORY_STORE_DB_ERROR: Failed to add category - " . $e->getMessage());
            $_SESSION['error_message']= "Error: ". $e->getMessage();
            $this->view('staff/categories/add', [
                // NEW: Re-populate form with submitted data
                'category' => (object)['name' => $name, 'description' => $description]
            ],'staff');
            return;
        }
    }

    /**
     * Displays the form to edit an existing category.
     * Accessible via /staff/categories/edit/{id}
     *
     * @param int $id The ID of the category to edit.
     * @return void
     */
    public function edit($id) {
        Logger::log("CATEGORY_EDIT: Attempting to display edit form for category ID: $id");

        $category = Category::find($id);

        if (!$category) {
            Logger::log("CATEGORY_EDIT_FAILED: Category ID $id not found for editing.");
            $_SESSION['error_message']= "Can't fint catergory " . $category->name .".";
            return $this->view('staff/categories_list', ['message' => 'Category not found.']);
        }

        Logger::log("CATEGORY_EDIT_SUCCESS: Displaying edit form for category ID: $id - {$category->name}");
        // Assuming your edit form uses 'category' variable for binding, like the add form
        $this->view('staff/categories/edit', [
            'category' => $category
        ], 'staff'); // Ensure 'staff' layout is applied here too
    }

    /**
     * Handles the POST request to update an existing category in the database.
     * Accessible via /staff/categories/update
     *
     * @return void
     */
    public function update() {
        Logger::log('CATEGORY_UPDATE: Attempting to update category.');

        // 1. Retrieve Input Data
        $id = trim($this->input('id')); // Hidden field for category ID
        $name = trim($this->input('name'));
        $description = trim($this->input('description')); // NEW: Retrieve description

        // 2. Retrieve the Category Model instance
        $category = Category::find($id);

        if (!$category) {
            Logger::log("CATEGORY_UPDATE_FAILED: Category ID $id not found for update.");
            $_SESSION['error_message']= "Catagory not found.";
            return $this->view('staff/categories/edit', ['message' => 'Category not found.']);
        }

        // 3. Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = 'Category Name is required.';
        } else {
            // Check if Category Name already exists for another category (excluding current category)
            $existingCategory = Category::where('name', $name)->where('id', '!=', $id)->first();
            if ($existingCategory) {
                $errors[] = 'Category Name already taken by another category.';
            }
        }

        // Optional: Add validation for description here if needed
        // if (!empty($description) && strlen($description) > 500) {
        //     $errors[] = 'Description cannot exceed 500 characters.';
        // }

        if (!empty($errors)) {
            Logger::log("CATEGORY_UPDATE_FAILED: Validation errors for Category ID $id: " . implode(', ', $errors));
            $_SESSION['error_message']= "ERROR: " . implode('<br>',$errors) ;
            $this->view('staff/categories/edit', [
                'category' => $category, // Pass the original category object back
            ], 'staff'); // Ensure 'staff' layout is applied here too
            return;
        }

        // 4. Assign new value and check for changes
        $category->name = $name;
        $category->description = $description; // NEW: Assign description

        if (!$category->isDirty()) {
            Logger::log("CATEGORY_UPDATE_INFO: Category ID $id submitted form with no changes.");

            $_SESSION['warning_message']="No changes were made.";
            $this->view('staff/categories/edit', [
                'category' => $category,
            ], 'staff'); // Ensure 'staff' layout is applied here too
            return;
        }

        // 5. Save Changes
        try {
            $category->save();
            Logger::log("CATEGORY_UPDATE_SUCCESS: Category '{$category->name}' (ID: {$category->id}) updated successfully.");

            $_SESSION['success_message']= "Successfully updated " . $category->name .".";

            header('Location: /staff/categories_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("CATEGORY_UPDATE_DB_ERROR: Failed to update category ID $id - " . $e->getMessage());
            $this->view('staff/categories/edit', [
                'category' => $category, // Pass the category object back
            ], 'staff'); // Ensure 'staff' layout is applied here too
            return;
        }
    }

    /**
     * Handles the deletion of a category.
     * Accessible via /staff/categories/delete/{id}
     *
     * @param int $id The ID of the category to delete.
     * @return void
     */
    public function delete($id) {
        Logger::log("CATEGORY_DELETE: Attempting to delete category ID: $id");

        $category = Category::find($id);

        if (!$category) {
            Logger::log("CATEGORY_DELETE_FAILED: Category ID $id not found for deletion.");
            $_SESSION['error_message'] = "Category not found.";
            header('Location: /staff/categories_list');
            exit();
        }

        try {
            $category->delete();
            Logger::log("CATEGORY_DELETE_SUCCESS: Category '{$category->name}' (ID: {$category->id}) deleted successfully.");

            $_SESSION['success_message']="Category successfully deleted.";
            header('Location: /staff/categories_list');
            exit();
        } catch (\Exception $e) {
            Logger::log("CATEGORY_DELETE_DB_ERROR: Failed to delete category ID $id - " . $e->getMessage());

            $_SESSION['error_message']="Failed to delete category." . " " . $e->getMessage();

            header('Location: /staff/categories_list');
            exit();
        }
    }
}