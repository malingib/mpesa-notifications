<?php $__env->startSection('title', 'Users Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                    Users Management
                </h2>
                <p class="mt-2 text-gray-600">Manage all system users and their access</p>
            </div>
            <a href="<?php echo e(route('admin.dashboard')); ?>" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo e($totalUsers ?? 0); ?></p>
                </div>
                <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.1s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Active Users</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo e($activeUsers ?? 0); ?></p>
                </div>
                <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-check text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.2s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Clients</p>
                    <p class="text-3xl font-bold text-blue-600"><?php echo e($totalClients ?? 0); ?></p>
                </div>
                <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-tie text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.3s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Admins</p>
                    <p class="text-3xl font-bold text-purple-600"><?php echo e($totalAdmins ?? 0); ?></p>
                </div>
                <div class="w-16 h-16 bg-purple-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-shield text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-list text-white"></i>
                    </div>
                    All Users
                </h3>
                <div class="flex items-center space-x-3">
                    <input type="text" id="searchInput" placeholder="Search users..." class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <select id="roleFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="client">Client</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">User</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Role</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Payments</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Created</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="usersTableBody">
                    <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-indigo-50 transition-colors duration-150 user-row" data-role="<?php echo e($user->role); ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center mr-3 text-white font-semibold">
                                        <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($user->name); ?></div>
                                        <div class="text-xs text-gray-500">ID: <?php echo e($user->id); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo e($user->email); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($user->isAdmin()): ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                        <i class="fas fa-user-shield mr-1"></i>Admin
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <i class="fas fa-user-tie mr-1"></i>Client
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if($user->is_active): ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Active
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-1"></i>Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e(number_format($user->payments_count ?? 0)); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo e($user->created_at->format('M d, Y')); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="text-yellow-600 hover:text-yellow-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if(!$user->isAdmin()): ?>
                                        <form action="<?php echo e(route('admin.impersonate', $user->id)); ?>" method="POST" class="inline" onsubmit="return confirm('Login as <?php echo e($user->name); ?>?')">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" class="text-green-600 hover:text-green-900" title="Login as this user">
                                                <i class="fas fa-arrow-right"></i>
                                            </button>
                                        </form>
                                        <form action="#" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-2"></i>
                                <p>No users found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($users->hasPages()): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <?php echo e($users->links()); ?>

            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    // Search and filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const roleFilter = document.getElementById('roleFilter');
        const tableBody = document.getElementById('usersTableBody');
        const rows = tableBody.querySelectorAll('.user-row');

        function filterUsers() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedRole = roleFilter.value;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const role = row.getAttribute('data-role');
                
                const matchesSearch = text.includes(searchTerm);
                const matchesRole = !selectedRole || role === selectedRole;

                row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);
    });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/zumi/php/mpesa-notifications/resources/views/admin/users.blade.php ENDPATH**/ ?>