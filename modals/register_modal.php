<!-- MODAL: SIGN UP -->
<div id="registerModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center"><i class="fa-solid fa-user-plus text-indigo-600 mr-2"></i> Create Account</h3>
            <button type="button" onclick="closeModal('registerModal')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register">
            <?php field('Full Name', 'full_name', 'text', 'required maxlength="100"');
                  field('Email Address', 'email', 'email', 'required');
                  field('Password (min 8 characters)', 'password', 'password', 'required minlength="8"');
                  field('Confirm Password', 'confirm', 'password', 'required minlength="8"'); ?>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('registerModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">Cancel</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Sign Up</button>
            </div>
            <p class="text-center text-sm text-gray-500">Already registered?
                <button type="button" onclick="switchModal('registerModal','loginModal')" class="text-indigo-600 hover:underline">Log in</button></p>
        </form>
    </div>
</div>
