@extends('layouts.app')

@section('title', 'Edit SMS Template')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-edit text-white text-xl"></i>
                    </div>
                    Edit SMS Template
                </h2>
                <p class="mt-2 text-gray-600">Update your SMS notification template</p>
            </div>
            <a href="{{ route('sms-templates.index') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Template Form -->
        <div class="lg:col-span-2">
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-50 to-pink-50">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-edit text-white"></i>
                        </div>
                        Template Details
                    </h3>
                </div>
                <form action="{{ route('sms-templates.update', $template->id) }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6">
                        <!-- Template Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Template Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="{{ old('name', $template->name) }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('name') border-red-500 @enderror"
                                placeholder="e.g., Payment Confirmation"
                                required
                            >
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Template Message -->
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                Template Message <span class="text-red-500">*</span>
                            </label>
                            <textarea 
                                id="message" 
                                name="message" 
                                rows="6"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('message') border-red-500 @enderror"
                                placeholder="Enter your SMS template message. Use {variables} like {amount}, {payer_name}, etc."
                                required
                            >{{ old('message', $template->message) }}</textarea>
                            <div class="mt-2 flex items-center justify-between">
                                <p class="text-xs text-gray-500">
                                    Use variables like {amount}, {payer_name}, {receipt}, etc. Click on variables in the sidebar to insert them.
                                </p>
                                <span class="text-xs text-gray-500" id="charCount">{{ strlen(old('message', $template->message)) }} / 500</span>
                            </div>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Options -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="is_active" 
                                    name="is_active" 
                                    value="1"
                                    {{ old('is_active', $template->is_active) ? 'checked' : '' }}
                                    class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                                >
                                <label for="is_active" class="ml-2 text-sm text-gray-700">
                                    Active
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="is_default" 
                                    name="is_default" 
                                    value="1"
                                    {{ old('is_default', $template->is_default) ? 'checked' : '' }}
                                    class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                                >
                                <label for="is_default" class="ml-2 text-sm text-gray-700">
                                    Set as Default
                                </label>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="bg-gray-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-medium text-gray-700">Live Preview</label>
                                <button 
                                    type="button" 
                                    id="refreshPreview"
                                    class="text-xs text-purple-600 hover:text-purple-800"
                                >
                                    <i class="fas fa-sync-alt mr-1"></i>Refresh
                                </button>
                            </div>
                            <div id="preview" class="bg-white border border-gray-200 rounded-lg p-4 min-h-[60px] text-sm text-gray-700">
                                Preview will appear here...
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                <span id="previewLength">0</span> characters
                            </p>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex items-center space-x-3">
                            <button 
                                type="submit" 
                                class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105"
                            >
                                <i class="fas fa-save mr-2"></i>Update Template
                            </button>
                            <a href="{{ route('sms-templates.index') }}" class="px-6 py-3 border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Available Variables Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in sticky top-4">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-blue-50">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-tags text-white text-sm"></i>
                        </div>
                        Available Variables
                    </h3>
                    <p class="text-xs text-gray-600 mt-1">Click to insert into template</p>
                </div>
                <div class="p-4 max-h-[600px] overflow-y-auto">
                    <div class="space-y-2">
                        @foreach($availableVariables as $variable)
                            <button 
                                type="button"
                                onclick="insertVariable('{{ $variable['tag'] }}')"
                                class="w-full text-left p-3 bg-gray-50 hover:bg-indigo-50 rounded-lg transition-colors group"
                            >
                                <div class="flex items-center justify-between">
                                    <code class="text-sm font-mono text-indigo-600 group-hover:text-indigo-700">{{ $variable['tag'] }}</code>
                                    <i class="fas fa-plus text-gray-400 group-hover:text-indigo-600 text-xs"></i>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">{{ $variable['description'] }}</p>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    const preview = document.getElementById('preview');
    const previewLength = document.getElementById('previewLength');
    const refreshPreviewBtn = document.getElementById('refreshPreview');

    // Character counter
    messageTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = `${length} / 500`;
        
        if (length > 500) {
            charCount.classList.add('text-red-600');
        } else {
            charCount.classList.remove('text-red-600');
        }
        
        updatePreview();
    });

    // Insert variable into template
    function insertVariable(tag) {
        const textarea = messageTextarea;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        
        textarea.value = text.substring(0, start) + tag + text.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + tag.length, start + tag.length);
        
        updatePreview();
        charCount.textContent = `${textarea.value.length} / 500`;
    }

    // Update preview
    function updatePreview() {
        const template = messageTextarea.value;
        
        if (!template.trim()) {
            preview.textContent = 'Preview will appear here...';
            previewLength.textContent = '0';
            return;
        }

        fetch('{{ route("sms-templates.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ message: template }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                preview.textContent = data.preview;
                previewLength.textContent = data.length;
                
                if (data.length > 160) {
                    preview.classList.add('text-red-600');
                    previewLength.classList.add('text-red-600');
                } else {
                    preview.classList.remove('text-red-600');
                    previewLength.classList.remove('text-red-600');
                }
            } else {
                preview.textContent = 'Error: ' + (data.message || 'Invalid template');
                preview.classList.add('text-red-600');
            }
        })
        .catch(error => {
            preview.textContent = 'Error loading preview';
            preview.classList.add('text-red-600');
        });
    }

    // Refresh preview button
    refreshPreviewBtn.addEventListener('click', updatePreview);

    // Initial preview
    updatePreview();
</script>
@endsection
