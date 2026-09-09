@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-cog text-white text-xl"></i>
                    </div>
                    Settings
                </h2>
                <p class="mt-2 text-gray-600">Manage your SMS and payment account settings</p>
            </div>
            <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- SMS Settings -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-sms text-white"></i>
                    </div>
                    SMS Settings
                </h3>
                <p class="text-sm text-gray-600 mt-1">Configure your Talksasa SMS API settings</p>
            </div>
            <form action="{{ route('settings.update-sms') }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Sender ID -->
                    <div>
                        <label for="sender_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Sender ID <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="sender_id" 
                            name="sender_id" 
                            value="{{ old('sender_id', $settings['sms']['sender_id'] ?? '') }}"
                            maxlength="11"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('sender_id') border-red-500 @enderror"
                            placeholder="YourName (max 11 characters)"
                            required
                        >
                        <p class="mt-1 text-xs text-gray-500">Alphanumeric string, max 11 characters</p>
                        @error('sender_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Talksasa API Token -->
                    <div>
                        <label for="talksasa_api_token" class="block text-sm font-medium text-gray-700 mb-2">
                            Talksasa API Token <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="talksasa_api_token" 
                                name="talksasa_api_token" 
                                value="{{ old('talksasa_api_token', $settings['sms']['talksasa_api_token'] ?? '') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('talksasa_api_token') border-red-500 @enderror pr-12"
                                placeholder="49|LNFe8WJ7CPtvl2mzowAB4ll4enbFR0XGgnQh2qWY"
                                required
                            >
                            <button 
                                type="button" 
                                onclick="togglePassword('talksasa_api_token')"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            >
                                <i class="fas fa-eye" id="talksasa_api_token_icon"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Get your API token from <a href="https://bulksms.talksasa.com" target="_blank" class="text-indigo-600 hover:underline">Talksasa Dashboard</a></p>
                        @error('talksasa_api_token')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Notification Phone Numbers -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-bell mr-2 text-indigo-600"></i>
                            Notification Phone Numbers
                        </label>
                        <p class="text-xs text-gray-500 mb-3">Add phone numbers that will receive confirmation SMS when customers make payments</p>
                        <div id="notificationNumbersContainer" class="space-y-2">
                            @php
                                $notificationNumbers = old('notification_numbers', $settings['sms']['notification_numbers'] ?? []);
                                if (empty($notificationNumbers)) {
                                    $notificationNumbers = [''];
                                }
                            @endphp
                            @foreach($notificationNumbers as $index => $number)
                                <div class="flex items-center gap-2 notification-number-row">
                                    <input 
                                        type="tel" 
                                        name="notification_numbers[]" 
                                        value="{{ $number }}"
                                        class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="254712345678 (with country code)"
                                        pattern="[0-9+]{10,15}"
                                    >
                                    @if($index > 0)
                                        <button 
                                            type="button" 
                                            onclick="removeNotificationNumber(this)"
                                            class="px-4 py-3 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors"
                                            title="Remove"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <button 
                            type="button" 
                            onclick="addNotificationNumber()"
                            class="mt-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm"
                        >
                            <i class="fas fa-plus mr-1"></i>Add Another Number
                        </button>
                        <p class="mt-2 text-xs text-gray-500">Format: Include country code (e.g., 254712345678 for Kenya)</p>
                    </div>

                    <!-- Test Connection Button -->
                    <div class="flex items-center space-x-3">
                        <button 
                            type="button" 
                            id="testSmsConnection"
                            class="flex-1 bg-gradient-to-r from-blue-500 to-cyan-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                        >
                            <i class="fas fa-plug mr-2"></i>Test Connection
                        </button>
                        <button 
                            type="submit" 
                            class="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                        >
                            <i class="fas fa-save mr-2"></i>Save SMS Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Payment Account Settings -->
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in" style="animation-delay: 0.1s">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-50 to-emerald-50">
                <h3 class="text-xl font-bold text-gray-900 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mr-3">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                    Payment Account Settings
                </h3>
                <p class="text-sm text-gray-600 mt-1">Configure your M-Pesa Paybill or Till number</p>
            </div>
            <form action="{{ route('settings.update-payment') }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Account Type -->
                    <div>
                        <label for="account_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Account Type <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="account_type" 
                            name="account_type" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('account_type') border-red-500 @enderror"
                            required
                        >
                            <option value="">Select account type</option>
                            <option value="paybill" {{ old('account_type', $settings['payment']['account_type'] ?? '') === 'paybill' ? 'selected' : '' }}>Paybill</option>
                            <option value="till" {{ old('account_type', $settings['payment']['account_type'] ?? '') === 'till' ? 'selected' : '' }}>Till Number</option>
                        </select>
                        @error('account_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Account Number -->
                    <div>
                        <label for="account_number" class="block text-sm font-medium text-gray-700 mb-2">
                            Account Number <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="account_number" 
                            name="account_number" 
                            value="{{ old('account_number', $settings['payment']['account_number'] ?? '') }}"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('account_number') border-red-500 @enderror"
                            placeholder="123456 or 1234567"
                            required
                        >
                        <p class="mt-1 text-xs text-gray-500">Your Paybill or Till number</p>
                        @error('account_number')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Business Shortcode (for Paybill URL registration) -->
                    <div>
                        <label for="business_shortcode" class="block text-sm font-medium text-gray-700 mb-2">
                            Business Shortcode (Optional)
                        </label>
                        <input 
                            type="text" 
                            id="business_shortcode" 
                            name="business_shortcode" 
                            value="{{ old('business_shortcode', $settings['payment']['business_shortcode'] ?? '') }}"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('business_shortcode') border-red-500 @enderror"
                            placeholder="6520448"
                        >
                        <p class="mt-1 text-xs text-gray-500">
                            <strong>Important:</strong> If your Consumer Key/Secret is tied to a Business Shortcode (not Till number), enter it here. 
                            URL registration will use this Shortcode instead of the Account Number.
                        </p>
                        @error('business_shortcode')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Consumer Key -->
                    <div>
                        <label for="consumer_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Consumer Key
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="consumer_key" 
                                name="consumer_key" 
                                value="{{ old('consumer_key', $settings['payment']['consumer_key'] ?? '') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('consumer_key') border-red-500 @enderror pr-12"
                                placeholder="Your M-Pesa Consumer Key"
                            >
                            <button 
                                type="button" 
                                onclick="togglePassword('consumer_key')"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            >
                                <i class="fas fa-eye" id="consumer_key_icon"></i>
                            </button>
                        </div>
                        @error('consumer_key')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Consumer Secret -->
                    <div>
                        <label for="consumer_secret" class="block text-sm font-medium text-gray-700 mb-2">
                            Consumer Secret
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="consumer_secret" 
                                name="consumer_secret" 
                                value="{{ old('consumer_secret', $settings['payment']['consumer_secret'] ?? '') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('consumer_secret') border-red-500 @enderror pr-12"
                                placeholder="Your M-Pesa Consumer Secret"
                            >
                            <button 
                                type="button" 
                                onclick="togglePassword('consumer_secret')"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            >
                                <i class="fas fa-eye" id="consumer_secret_icon"></i>
                            </button>
                        </div>
                        @error('consumer_secret')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Passkey -->
                    <div>
                        <label for="passkey" class="block text-sm font-medium text-gray-700 mb-2">
                            Passkey
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="passkey" 
                                name="passkey" 
                                value="{{ old('passkey', $settings['payment']['passkey'] ?? '') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('passkey') border-red-500 @enderror pr-12"
                                placeholder="Your M-Pesa Passkey"
                            >
                            <button 
                                type="button" 
                                onclick="togglePassword('passkey')"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                            >
                                <i class="fas fa-eye" id="passkey_icon"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Required for Paybill accounts</p>
                        @error('passkey')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Webhook URLs Section -->
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-link mr-2 text-indigo-600"></i>
                            Webhook URLs Configuration
                        </h4>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                            <p class="text-xs text-blue-800 flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5"></i>
                                <span>
                                    <strong>Shared URL Approach:</strong> All users register the same webhook URL. 
                                    The system automatically routes payments to the correct user based on your Till/Paybill number in the M-Pesa payload. 
                                    URLs must be publicly accessible (use ngrok for local testing).
                                </span>
                            </p>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Confirmation URL
                                </label>
                                <input 
                                    type="url" 
                                    id="confirmation_url" 
                                    value="{{ url('/api/webhooks/mpesa/payment') }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="https://your-domain.com/api/webhooks/mpesa/payment"
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Validation URL
                                </label>
                                <input 
                                    type="url" 
                                    id="validation_url" 
                                    value="{{ url('/api/webhooks/mpesa/payment') }}"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="https://your-domain.com/api/webhooks/mpesa/payment"
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    Response Type
                                </label>
                                <select 
                                    id="response_type" 
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                >
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Test, Register URLs & Save Buttons -->
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <button 
                                type="button" 
                                id="testPaymentCredentials"
                                class="flex-1 bg-gradient-to-r from-blue-500 to-cyan-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                            >
                                <i class="fas fa-plug mr-2"></i>Test Credentials
                            </button>
                            <button 
                                type="button" 
                                id="registerUrls"
                                class="flex-1 bg-gradient-to-r from-purple-500 to-pink-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                            >
                                <i class="fas fa-link mr-2"></i>Register URLs
                            </button>
                        </div>
                        <button 
                            type="submit" 
                            class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                        >
                            <i class="fas fa-save mr-2"></i>Save Payment Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '_icon');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Test SMS Connection
    document.getElementById('testSmsConnection').addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        const tokenInput = document.getElementById('talksasa_api_token');
        const token = tokenInput.value;
        
        if (!token) {
            alert('❌ Please enter your Talksasa API token first.');
            tokenInput.focus();
            return;
        }
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Testing...';
        
        fetch('{{ route("settings.test-sms") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token: token }),
        })
        .then(response => response.json())
        .then(data => {
            button.disabled = false;
            button.innerHTML = originalText;
            
            if (data.success) {
                alert('✅ ' + data.message);
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            button.disabled = false;
            button.innerHTML = originalText;
            alert('❌ Connection test failed: ' + error.message);
        });
    });

    // Test Payment Credentials
    document.getElementById('testPaymentCredentials').addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        
        // Get form values
        const accountType = document.getElementById('account_type').value;
        const accountNumber = document.getElementById('account_number').value;
        const businessShortcode = document.getElementById('business_shortcode') ? document.getElementById('business_shortcode').value : null;
        const consumerKey = document.getElementById('consumer_key').value;
        const consumerSecret = document.getElementById('consumer_secret').value;
        const passkey = document.getElementById('passkey').value;
        
        if (!accountType || !accountNumber) {
            alert('❌ Please fill in Account Type and Account Number first.');
            return;
        }
        
        if (!consumerKey || !consumerSecret) {
            alert('❌ Please enter Consumer Key and Consumer Secret first.');
            return;
        }
        
        // For Paybill, check if passkey is required for STK push
        if (accountType === 'paybill' && !passkey) {
            const proceed = confirm('⚠️ Passkey is required for Paybill STK Push test.\n\n' +
                'Do you want to proceed with authentication test only?\n\n' +
                'OK = Authentication test only\n' +
                'Cancel = Go back to add Passkey');
            if (!proceed) {
                return;
            }
        }
        
        // Ask user if they want to test with STK Push
        const initiateStkPush = confirm('Do you want to test with STK Push?\n\n' +
            '✅ Yes: Will send a 5 KES payment prompt to your phone\n' +
            '❌ No: Will only test authentication\n\n' +
            'Click OK to test with STK Push, or Cancel for authentication only.');
        
        let phoneNumber = null;
        if (initiateStkPush) {
            phoneNumber = prompt('Enter your phone number to receive the STK Push:\n\nFormat: 254712345678 or 0712345678\n\n' +
                'This will send a 5 KES payment prompt to verify your credentials.');
            if (!phoneNumber) {
                alert('❌ Phone number is required for STK Push test.');
                return;
            }
            
            // Validate phone number format
            const phoneRegex = /^(254|0)[0-9]{9}$/;
            const cleanPhone = phoneNumber.replace(/[^0-9]/g, '');
            if (!phoneRegex.test(cleanPhone)) {
                alert('❌ Invalid phone number format. Use: 254712345678 or 0712345678');
                return;
            }
        }
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + (initiateStkPush ? 'Initiating STK Push...' : 'Testing...');
        
        fetch('{{ route("settings.test-payment") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                account_type: accountType,
                account_number: accountNumber,
                business_shortcode: businessShortcode,
                consumer_key: consumerKey,
                consumer_secret: consumerSecret,
                passkey: passkey,
                phone_number: phoneNumber,
                initiate_stk_push: initiateStkPush,
            }),
        })
        .then(response => response.json())
        .then(data => {
            button.disabled = false;
            button.innerHTML = originalText;
            
            if (data.success) {
                if (data.stk_push_initiated) {
                    alert('✅ ' + data.message + '\n\n' +
                        'Checkout Request ID: ' + (data.checkout_request_id || 'N/A') + '\n\n' +
                        'Please check your phone and complete the payment to verify credentials.');
                } else {
                    alert('✅ ' + data.message);
                }
            } else {
                let errorMsg = '❌ ' + data.message;
                if (data.auth_successful && data.stk_push_failed) {
                    errorMsg += '\n\nNote: Authentication succeeded, but STK Push failed. Check your Passkey and ShortCode.';
                }
                alert(errorMsg);
            }
        })
        .catch(error => {
            button.disabled = false;
            button.innerHTML = originalText;
            alert('❌ Connection test failed: ' + error.message);
        });
    });

    // Register URLs with M-Pesa
    document.getElementById('registerUrls').addEventListener('click', function() {
        const button = this;
        const originalText = button.innerHTML;
        
        // Get form values
        const accountType = document.getElementById('account_type').value;
        const accountNumber = document.getElementById('account_number').value;
        const businessShortcode = document.getElementById('business_shortcode').value;
        const consumerKey = document.getElementById('consumer_key').value;
        const consumerSecret = document.getElementById('consumer_secret').value;
        const confirmationUrl = document.getElementById('confirmation_url').value;
        const validationUrl = document.getElementById('validation_url').value;
        const responseType = document.getElementById('response_type').value;
        
        // Use Business Shortcode for URL registration if provided, otherwise use Account Number
        // This is important because Consumer Key/Secret is tied to Business Shortcode
        const shortCodeForRegistration = businessShortcode || accountNumber;
        
        if (!accountType || !accountNumber) {
            alert('❌ Please fill in Account Type and Account Number first.');
            return;
        }
        
        if (!consumerKey || !consumerSecret) {
            alert('❌ Please enter Consumer Key and Consumer Secret first.');
            return;
        }
        
        if (!confirmationUrl || !validationUrl) {
            alert('❌ Please enter both Confirmation URL and Validation URL.');
            return;
        }
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Registering...';
        
        // Log what we're sending for debugging
        console.log('Registering URLs with:', {
            account_type: accountType,
            account_number: accountNumber,
            business_shortcode: businessShortcode,
            shortcode_for_registration: shortCodeForRegistration,
        });
        
        fetch('{{ route("settings.register-urls") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                account_type: accountType,
                account_number: accountNumber, // Original account number
                business_shortcode: businessShortcode || null, // Business Shortcode if provided
                consumer_key: consumerKey,
                consumer_secret: consumerSecret,
                confirmation_url: confirmationUrl,
                validation_url: validationUrl,
                response_type: responseType,
            }),
        })
        .then(response => response.json())
        .then(data => {
            button.disabled = false;
            button.innerHTML = originalText;
            
            // Display response with payload
            showRegisterUrlResponse(data);
        })
        .catch(error => {
            button.disabled = false;
            button.innerHTML = originalText;
            showRegisterUrlResponse({
                success: false,
                message: 'Connection failed: ' + error.message,
                payload: null,
            });
        });
    });

    // Show Register URL Response with Payload
    function showRegisterUrlResponse(data) {
        const statusIcon = data.success ? '✅' : '❌';
        const statusColor = data.success ? 'text-green-600' : 'text-red-600';
        const bgColor = data.success ? 'bg-green-50' : 'bg-red-50';
        
        let payloadHtml = '';
        if (data.payload) {
            payloadHtml = `
                <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <h5 class="text-sm font-semibold text-gray-700 mb-2">Response Payload:</h5>
                    <pre class="text-xs text-gray-800 overflow-auto max-h-64 bg-white p-3 rounded border">${JSON.stringify(data.payload, null, 2)}</pre>
                </div>
            `;
        }
        
        const modalHtml = `
            <div id="registerUrlModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: flex;">
                <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="p-6 ${bgColor} border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold ${statusColor} flex items-center">
                                <span class="mr-2">${statusIcon}</span>
                                URL Registration ${data.success ? 'Success' : 'Failed'}
                            </h3>
                            <button onclick="closeRegisterUrlModal()" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-700 mb-4">${data.message || 'No message provided'}</p>
                        ${payloadHtml}
                        <div class="mt-6 flex justify-end">
                            <button onclick="closeRegisterUrlModal()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('registerUrlModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    // Close modal function
    function closeRegisterUrlModal() {
        const modal = document.getElementById('registerUrlModal');
        if (modal) {
            modal.remove();
        }
    }

    // Add notification number field
    function addNotificationNumber() {
        const container = document.getElementById('notificationNumbersContainer');
        const newRow = document.createElement('div');
        newRow.className = 'flex items-center gap-2 notification-number-row';
        newRow.innerHTML = `
            <input 
                type="tel" 
                name="notification_numbers[]" 
                value=""
                class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                placeholder="254712345678 (with country code)"
                pattern="[0-9+]{10,15}"
            >
            <button 
                type="button" 
                onclick="removeNotificationNumber(this)"
                class="px-4 py-3 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors"
                title="Remove"
            >
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(newRow);
    }

    // Remove notification number field
    function removeNotificationNumber(button) {
        const row = button.closest('.notification-number-row');
        if (row) {
            row.remove();
        }
    }
</script>
@endsection
