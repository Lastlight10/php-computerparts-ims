<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Models\Category;

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
        $name = $this->input('name');
        $description = $this->input('description'); // NEW: Retrieve description

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
            // Pass back input data to re-populate form fields
            $this->view('staff/categories/add', [
                'error' => implode('<br>', $errors),
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
            // Redirect to category list, which you indicated is handled by StaffController
            header('Location: /staff/categories_list?success_message=' . urlencode('Category added successfully!'));
            exit();

        } catch (\Exception $e) {
            Logger::log("CATEGORY_STORE_DB_ERROR: Failed to add category - " . $e->getMessage());
            $this->view('staff/categories/add', [
                'error' => 'An error occurred while adding the category. Please try again. ' . $e->getMessage(),
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
            return $this->view('errors/404', ['message' => 'Category not found.']);
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
        $id = $this->input('id'); // Hidden field for category ID
        $name = $this->input('name');
        $description = $this->input('description'); // NEW: Retrieve description

        // 2. Retrieve the Category Model instance
        $category = Category::find($id);

        if (!$category) {
            Logger::log("CATEGORY_UPDATE_FAILED: Category ID $id not found for update.");
            return $this->view('errors/404', ['message' => 'Category not found.']);
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
            $this->view('staff/categories/edit', [
                'error' => implode('<br>', $errors),
                'category' => $category, // Pass the original category object back
            ], 'staff'); // Ensure 'staff' layout is applied here too
            return;
        }

        // 4. Assign new value and check for changes
        $category->name = $name;
        $category->description = $description; // NEW: Assign description

        if (!$category->isDirty()) {
            Logger::log("CATEGORY_UPDATE_INFO: Category ID $id submitted form with no changes.");
            $this->view('staff/categories/edit', [
                'success_message' => 'No changes were made to the category.',
                'category' => $category,
            ], 'staff'); // Ensure 'staff' layout is applied here too
            return;
        }

        // 5. Save Changes
        try {
            $category->save();
            Logger::log("CATEGORY_UPDATE_SUCCESS: Category '{$category->name}' (ID: {$category->id}) updated successfully.");
            header('Location: /staff/categories_list?success_message=' . urlencode('Category updated successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("CATEGORY_UPDATE_DB_ERROR: Failed to update category ID $id - " . $e->getMessage());
            $this->view('staff/categories/edit', [
                'error' => 'An error occurred while updating the category. Please try again. ' . $e->getMessage(),
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
            header('Location: /staff/categories_list?error=' . urlencode('Category not found for deletion.'));
            exit();
        }

        try {
            // IMPORTANT: Foreign Key Constraints Check! (Keep this important comment for future reference)
            // If you have products linked to this category, deleting the category
            // will cause a foreign key constraint violation error unless:
            // 1. Your database's foreign key is set to ON DELETE CASCADE (unlikely desired for categories).
            // 2. You manually dissociate or delete related products first (e.g., Product::where('category_id', $id)->update(['category_id' => null]);).
            // 3. You prevent deletion if related products exist.
            //    Example of preventing deletion if products exist (assuming 'products' relationship in Category model):
            //    if ($category->products()->count() > 0) { // requires 'products' relationship in Category model
            //        Logger::log("CATEGORY_DELETE_FAILED: Category ID $id has associated products and cannot be deleted.");
            //        header('Location: /staff/categories_list?error=' . urlencode('Cannot delete category because it has associated products. Please reassign products first.'));
            //        exit();
            //    }

            $category->delete();
            Logger::log("CATEGORY_DELETE_SUCCESS: Category '{$category->name}' (ID: {$category->id}) deleted successfully.");
            header('Location: /staff/categories_list?success_message=' . urlencode('Category deleted successfully!'));
            exit();
        } catch (\Exception $e) {
            Logger::log("CATEGORY_DELETE_DB_ERROR: Failed to delete category ID $id - " . $e->getMessage());
            header('Location: /staff/categories_list?error=' . urlencode('An error occurred while deleting the category: ' . $e->getMessage()));
            exit();
        }
    }
}