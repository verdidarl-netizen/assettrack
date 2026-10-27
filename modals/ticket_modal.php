<?php modal_start('ticketModal', 'New Maintenance Ticket', 'Update Ticket', 'fa-screwdriver-wrench', 'ticket_save'); ?>
<?php select_field('Asset', 'asset_id', $assetOptions, false, 'required');
      field('Title', 'title', 'text', 'required maxlength="150"');
      field('Issue Description', 'issue_description', 'textarea');
      select_field('Priority', 'priority', array_combine(PRIORITIES, PRIORITIES), false); ?>
<?php if (Auth::is('Admin', 'Technician')): ?>
<div class="grid grid-cols-2 gap-3">
    <?php select_field('Type', 'type', array_combine(TICKET_TYPES, TICKET_TYPES), false);
          select_field('Status', 'status', array_combine(TICKET_STATUS, TICKET_STATUS), false);
          select_field('Technician', 'assigned_technician_id', $techOptions);
          field('Scheduled Date', 'scheduled_date', 'date');
          field('Cost (₱)', 'cost', 'number', 'min="0" step="0.01"'); ?>
</div>
<?php endif; modal_end('Save Ticket'); ?>
