<!-- MODAL: LOGIN -->
<div id="loginModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center"><i class="fa-solid fa-right-to-bracket text-indigo-600 mr-2"></i> User Login</h3>
            <button type="button" onclick="closeModal('loginModal')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <!-- Demo Credentials Quick-Fill (remove for production) -->
        <div class="mb-4 bg-indigo-50 border border-indigo-100 rounded-lg p-3 text-xs text-indigo-900">
            <p class="font-semibold mb-1"><i class="fa-solid fa-info-circle mr-1"></i> Quick Demo Login:</p>
            <div class="flex flex-wrap gap-1.5 mt-2">
                <button type="button" onclick="fillCredentials('david.vance@company.com', 'password123')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded">Admin</button>
                <button type="button" onclick="fillCredentials('tech@company.com', 'password123')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded">Technician</button>
                <button type="button" onclick="fillCredentials('employee@company.com', 'password123')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded">Employee</button>
            </div>
        </div>

        <form action="" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" id="loginEmail" name="email" required placeholder="e.g. david.vance@company.com" class="<?= INPUT_CLASS ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="loginPassword" name="password" required placeholder="••••••••" class="<?= INPUT_CLASS ?>">
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeModal('loginModal')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">Cancel</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Login</button>
            </div>
            <p class="text-center text-sm text-gray-500">No account?
                <button type="button" onclick="switchModal('loginModal','registerModal')" class="text-indigo-600 hover:underline">Sign up</button></p>
        </form>
    </div>
</div>
