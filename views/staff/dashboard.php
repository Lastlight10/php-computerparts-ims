
  <div class="main-content dark-bg">
    <div class="topbar d-flex justify-content-between align-items-center">
      <div class="light-txt"><strong>Welcome, <?= htmlspecialchars($username)?></strong></div>
    </div>

    <div class="content light-txt">
      <h2 class="text-white mb-4">Dashboard</h2>
      <?php
          if (isset($_SESSION['success_message'])) {
              echo '
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['success_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['success_message']); // fix: previously unsetting error instead
          }

          if (isset($_SESSION['error_message'])) {
              echo '
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['error_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['error_message']);
          }
          ?>
      <!-- Example cards -->
      <div class="row">
        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Products</h5>
              <p class="card-text"><?= htmlspecialchars($products_count) ?>  items in stock</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Transactions</h5>
              <p class="card-text"><?= htmlspecialchars($transaction_count) ?> in total</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Items</h5>
              <p class="card-text"><?= htmlspecialchars($items_count) ?> in total</p>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-4 shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Users</h5>
              <p class="card-text"><?= htmlspecialchars($count) ?> active users</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  ></script>
<?php 
use App\Core\Logger;
Logger::log('UI: On dashboard.php')
?>