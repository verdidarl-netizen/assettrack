<?php modal_start('userModal', 'Add User', 'Edit User', 'fa-user', 'user_save', 'max-w-md');
      field('Full Name', 'full_name', 'text', 'required');
      field('Email', 'email', 'email', 'required');
      field('Password (leave blank to keep current when editing)', 'password', 'password', 'minlength="8" autocomplete="new-password"');
      select_field('Role', 'role', array_combine(ROLES, ROLES), false);
      select_field('Department', 'department_id', $departments);
      modal_end('Save User'); ?>
