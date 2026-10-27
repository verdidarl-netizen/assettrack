<?php modal_start('profileModal', 'My Profile', 'My Profile', 'fa-id-card', 'profile_save', 'max-w-md');
      field('Full Name', 'full_name', 'text', 'required');
      field('Email', 'email', 'email', 'required');
      select_field('Department', 'department_id', $departments);
      field('New Password (optional)', 'password', 'password', 'minlength="8" autocomplete="new-password"'); ?>
<label class="flex items-center text-sm text-gray-700"><input type="checkbox" name="notify_enabled" value="1" class="mr-2"> Send me maintenance &amp; warranty notifications</label>
<?php modal_end('Update Profile'); ?>
