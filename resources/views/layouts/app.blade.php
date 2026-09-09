<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Talksasa') }} - @yield('title', 'Dashboard')</title>
    
    <!-- Preload critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- Stylesheets -->
    <!-- Note: Tailwind CDN is for development only. For production, install Tailwind CSS as PostCSS plugin -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- Defer Chart.js loading -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        [x-cloak] { display: none !important; }
        * {
            font-family: 'Inter', sans-serif;
        }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Optimize chart rendering */
        canvas {
            max-width: 100%;
            height: auto !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
    <div class="min-h-screen">
        <!-- Modern Navigation -->
        <nav class="bg-white/80 backdrop-blur-lg shadow-lg border-b border-gray-200/50 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <div class="flex items-center space-x-2">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-money-bill-wave text-white text-lg"></i>
                                </div>
                                <h1 class="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                                    Talksasa
                                </h1>
                            </div>
                        </div>
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-1">
                            @auth
                                @if(auth()->user()->isAdmin())
                                    <a href="{{ route('admin.dashboard') }}" class="text-indigo-600 border-indigo-500 inline-flex items-center px-4 py-2 border-b-2 text-sm font-semibold transition-all duration-200">
                                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                                    </a>
                                    <a href="{{ route('admin.users') }}" class="text-gray-600 hover:text-indigo-600 border-transparent hover:border-indigo-300 inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium transition-all duration-200">
                                        <i class="fas fa-users mr-2"></i>Users
                                    </a>
                                    <a href="{{ route('admin.payments') }}" class="text-gray-600 hover:text-indigo-600 border-transparent hover:border-indigo-300 inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium transition-all duration-200">
                                        <i class="fas fa-credit-card mr-2"></i>Payments
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}" class="text-indigo-600 border-indigo-500 inline-flex items-center px-4 py-2 border-b-2 text-sm font-semibold transition-all duration-200">
                                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                                    </a>
                                    <a href="{{ route('payments.index') }}" class="text-gray-600 hover:text-indigo-600 border-transparent hover:border-indigo-300 inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium transition-all duration-200">
                                        <i class="fas fa-credit-card mr-2"></i>Payments
                                    </a>
                                    <a href="{{ route('merchants.index') }}" class="text-gray-600 hover:text-indigo-600 border-transparent hover:border-indigo-300 inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium transition-all duration-200">
                                        <i class="fas fa-store mr-2"></i>Merchants
                                    </a>
                                    <a href="{{ route('sms-templates.index') }}" class="text-gray-600 hover:text-indigo-600 border-transparent hover:border-indigo-300 inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium transition-all duration-200">
                                        <i class="fas fa-sms mr-2"></i>Templates
                                    </a>
                                    <a href="{{ route('settings.index') }}" class="text-gray-600 hover:text-indigo-600 border-transparent hover:border-indigo-300 inline-flex items-center px-4 py-2 border-b-2 text-sm font-medium transition-all duration-200">
                                        <i class="fas fa-cog mr-2"></i>Settings
                                    </a>
                                    <!-- SMS Balance -->
                                    <div class="inline-flex items-center px-4 py-2 border-b-2 border-transparent text-sm font-medium">
                                        <div class="flex items-center space-x-2 bg-gradient-to-r from-green-50 to-emerald-50 px-3 py-1 rounded-lg">
                                            <i class="fas fa-coins text-green-600"></i>
                                            <span class="text-gray-700 font-semibold">SMS:</span>
                                            <span class="text-green-600 font-bold" id="smsBalance">
                                                <i class="fas fa-spinner fa-spin"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <a href="#" onclick="buySmsCredits(); return false;" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                                        <i class="fas fa-shopping-cart mr-2"></i>Buy Now
                                    </a>
                                @endif
                            @endauth
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        @auth
                            @if(session('impersonating'))
                                <!-- Impersonation Banner -->
                                <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg mr-2 flex items-center space-x-2">
                                    <i class="fas fa-user-secret"></i>
                                    <span class="text-sm font-medium">Impersonating: {{ auth()->user()->name }}</span>
                                </div>
                                <form method="POST" action="{{ route('stop-impersonating') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="bg-gradient-to-r from-red-500 to-pink-600 text-white px-4 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                                        <i class="fas fa-undo mr-2"></i>Return to Admin
                                    </button>
                                </form>
                            @else
                                <div class="flex items-center space-x-3">
                                    <div class="hidden sm:block text-right">
                                        <p class="text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                                        <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                                    </div>
                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold shadow-lg">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="text-gray-600 hover:text-red-600 transition-colors duration-200 p-2 hover:bg-red-50 rounded-lg">
                                            <i class="fas fa-sign-out-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="py-8">
            @if(session('success'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-green-400 to-emerald-500 text-white px-6 py-4 rounded-xl shadow-lg flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-2xl mr-3"></i>
                            <span class="font-medium">{{ session('success') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-red-400 to-pink-500 text-white px-6 py-4 rounded-xl shadow-lg flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                            <span class="font-medium">{{ session('error') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6 animate-fade-in">
                    <div class="bg-gradient-to-r from-red-400 to-pink-500 text-white px-6 py-4 rounded-xl shadow-lg">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                            <span class="font-semibold">Please fix the following errors:</span>
                        </div>
                        <ul class="list-disc list-inside ml-8">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @yield('scripts')
    
    @auth
        @if(!auth()->user()->isAdmin())
            <script>
                // Function to load SMS balance dynamically
                function loadSmsBalance() {
                    const balanceElement = document.getElementById('smsBalance');
                    if (!balanceElement) return;

                    // Show loading spinner
                    balanceElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    fetch('{{ route("settings.balance") }}', {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Balance API response:', data);
                        console.log('Debug raw data:', JSON.stringify(data.debug, null, 2));
                        
                        if (data.success && data.balance !== undefined && data.balance !== 'N/A') {
                            balanceElement.textContent = data.balance;
                        } else {
                            balanceElement.textContent = 'N/A';
                            if (data.message) {
                                console.warn('Balance fetch warning:', data.message);
                            }
                            if (data.debug) {
                                console.log('Debug info:', data.debug);
                                console.log('Raw balance value:', data.debug.raw_balance);
                                console.log('Raw API data:', data.debug.raw_data);
                                
                                // Try to manually extract balance for debugging
                                if (data.debug.raw_data) {
                                    const rawData = data.debug.raw_data;
                                    console.log('Attempting to extract balance from:', rawData);
                                    
                                    if (rawData.data !== undefined) {
                                        console.log('Found data field:', rawData.data, 'Type:', typeof rawData.data);
                                    }
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Failed to fetch balance:', error);
                        balanceElement.textContent = 'N/A';
                    });
                }

                // Function to buy SMS credits (placeholder for future implementation)
                function buySmsCredits() {
                    // TODO: Implement SMS credit purchase flow
                    alert('SMS credit purchase feature coming soon!');
                }

                // Load balance on page load
                document.addEventListener('DOMContentLoaded', function() {
                    loadSmsBalance();
                });

                // Auto-refresh balance every 5 minutes
                setInterval(function() {
                    loadSmsBalance();
                }, 300000); // 5 minutes
            </script>
        @endif
    @endauth
</body>
</html>
