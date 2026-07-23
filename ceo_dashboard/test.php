           <!-- Detailed Expenses Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">All Expenses</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="expensesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Expense Name</th>
                                    <th>Vendor/Supplier</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                         $count = 0;
foreach ($all_expenses as $expense):
    if ($count++ >= 500) break;
?>
<tr>
    <td><?= date('Y-m-d H:i', strtotime($expense['expense_date'])); ?></td>
    <td><span class="badge bg-secondary"><?= htmlspecialchars($expense['category']); ?></span></td>
    <td><?= htmlspecialchars($expense['expense_name']); ?></td>
    <td><?= htmlspecialchars($expense['vendor_supplier'] ?: '-'); ?></td>
    <td class="text-danger fw-bold">
        <span class="currency-symbol">$</span>
        <span class="amount" data-usd="<?= $expense['amount']; ?>">
            <?= number_format($expense['amount'], 2); ?>
        </span>
    </td>
    <td><?= htmlspecialchars($expense['payment_method'] ?: '-'); ?></td>
    <td><?= htmlspecialchars($expense['reference_number'] ?: '-'); ?></td>
    <td>
        <small>
            <?= htmlspecialchars(substr($expense['description'], 0, 50)); ?>
            <?= strlen($expense['description']) > 50 ? '...' : ''; ?>
        </small>
    </td>
    <td>
        <button class="btn btn-sm btn-outline-primary" onclick="viewExpense(<?= $expense['expense_id']; ?>)">
            <i class="bi bi-eye"></i>
        </button>
    </td>
</tr>
<?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>