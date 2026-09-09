@extends('layouts.app')

@section('title', 'SMS Templates')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                        <i class="fas fa-sms text-white text-xl"></i>
                    </div>
                    SMS Templates
                </h2>
                <p class="mt-2 text-gray-600">Manage your SMS notification templates</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('dashboard') }}" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
                <a href="{{ route('sms-templates.create') }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>New Template
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Templates</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($totalTemplates ?? 0) }}</p>
                </div>
                <div class="w-16 h-16 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-list text-indigo-600 text-2xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl p-6 border border-gray-200/50 animate-fade-in" style="animation-delay: 0.1s">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Active Templates</p>
                    <p class="text-3xl font-bold text-green-600">{{ number_format($activeTemplates ?? 0) }}</p>
                </div>
                <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($templates as $template)
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in hover:shadow-2xl transition-all duration-200">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">{{ $template->name }}</h3>
                        @if($template->is_active)
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>Active
                            </span>
                        @else
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                <i class="fas fa-pause-circle mr-1"></i>Inactive
                            </span>
                        @endif
                    </div>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-700 mb-4 line-clamp-3">{{ $template->message }}</p>
                    @if($template->is_default)
                        <div class="mb-2">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                <i class="fas fa-star mr-1"></i>Default Template
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
                        <span><i class="fas fa-calendar mr-1"></i>{{ $template->created_at->format('M d, Y') }}</span>
                        <span><i class="fas fa-edit mr-1"></i>{{ $template->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('sms-templates.edit', $template->id) }}" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors text-center">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </a>
                        <button 
                            type="button"
                            onclick="previewTemplate('{{ $template->id }}', '{{ addslashes($template->message) }}')"
                            class="flex-1 bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-eye mr-1"></i>Preview
                        </button>
                        <form 
                            action="{{ route('sms-templates.destroy', $template->id) }}" 
                            method="POST" 
                            class="inline"
                            onsubmit="return confirm('Are you sure you want to delete this template?')"
                        >
                            @csrf
                            @method('DELETE')
                            <button 
                                type="submit" 
                                class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                                title="Delete Template"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-12 text-center animate-fade-in">
                <i class="fas fa-sms text-6xl text-gray-400 mb-4"></i>
                <p class="text-xl font-semibold text-gray-600 mb-2">No templates found</p>
                <p class="text-gray-500 mb-6">Create your first SMS template to get started</p>
                <a href="{{ route('sms-templates.create') }}" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-medium hover:shadow-lg transition-all duration-200 inline-block">
                    <i class="fas fa-plus mr-2"></i>Create Template
                </a>
            </div>
        @endforelse
    </div>

    @if($templates->hasPages())
        <div class="mt-8">
            <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-xl border border-gray-200/50 p-4">
                {{ $templates->links() }}
            </div>
        </div>
    @endif
</div>

<!-- Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 animate-fade-in">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-900">Template Preview</h3>
            <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="previewContent" class="bg-gray-50 rounded-lg p-4 mb-4 min-h-[100px] text-sm text-gray-700">
            Loading preview...
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
            <span id="previewCharCount">0 characters</span>
            <span id="previewSmsCount">0 SMS</span>
        </div>
        <button onclick="closePreviewModal()" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
            Close
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function previewTemplate(id, message) {
        const modal = document.getElementById('previewModal');
        const content = document.getElementById('previewContent');
        const charCount = document.getElementById('previewCharCount');
        const smsCount = document.getElementById('previewSmsCount');
        
        modal.classList.remove('hidden');
        content.textContent = 'Loading preview...';
        
        fetch('{{ route("sms-templates.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ message: message }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.textContent = data.preview;
                charCount.textContent = data.length + ' characters';
                smsCount.textContent = Math.ceil(data.length / 160) + ' SMS';
            } else {
                content.textContent = 'Error: ' + (data.message || 'Invalid template');
                content.classList.add('text-red-600');
            }
        })
        .catch(error => {
            content.textContent = 'Error loading preview';
            content.classList.add('text-red-600');
        });
    }
    
    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
    }
</script>
@endsection
