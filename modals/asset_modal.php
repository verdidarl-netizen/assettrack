<?php modal_start('assetModal', 'Add Asset', 'Edit Asset', 'fa-laptop', 'asset_save', 'max-w-2xl'); ?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <?php field('Asset Tag', 'asset_tag', 'text', 'required');
          field('Name', 'name', 'text', 'required');
          field('Serial Number', 'serial_number', 'text', 'required');
          select_field('Category', 'category_id', $categories);
          select_field('Assigned To', 'assigned_user_id', $userOptions);
          select_field('Status', 'status', array_combine(ASSET_STATUS, ASSET_STATUS), false);
          field('Location', 'location', 'text', 'maxlength="100"');
          field('Purchase Date', 'purchase_date', 'date');
          field('Purchase Cost (₱)', 'purchase_cost', 'number', 'min="0" step="0.01"');
          field('Salvage Value (₱)', 'salvage_value', 'number', 'min="0" step="0.01"');
          field('Useful Life (years)', 'useful_life_years', 'number', 'min="1" value="5"');
          field('Warranty Expiry', 'warranty_expiry', 'date'); ?>
</div>
<?php field('Notes', 'notes', 'textarea'); modal_end('Save Asset'); ?>
