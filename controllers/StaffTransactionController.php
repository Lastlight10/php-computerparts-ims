<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;

use Models\Transaction;
require_once 'vendor/autoload.php';

class StaffTransactionController extends Controller {
  public function show($id) {
      // Make sure your Transaction model has the relationship defined:
      // public function items() { return $this->hasMany(TransactionItem::class); }
      // public function product() { return $this->belongsTo(Product::class); } // In TransactionItem model
      // public function instances() { return $this->hasMany(ProductInstance::class); } // In TransactionItem model, for serialized items bought/sold in this line item

      $transaction = Transaction::with(['items.product', 'items.instances'])->find($id);

      if (!$transaction) {
          // Handle transaction not found
          return $this->view('errors/404');
      }

      $this->view('staff/transactions/show', ['transaction' => $transaction]);
  }
}
