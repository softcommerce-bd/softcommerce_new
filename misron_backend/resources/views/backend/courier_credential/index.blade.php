@extends('backend.layouts.app')

@section('content')
    <div class="page-content" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; padding: 30px;">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="text-center">
                        <h2 class="display-5 fw-bold" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            Courier Settings Management
                        </h2>
                        <p class="text-muted">Configure your delivery partners' API credentials securely</p>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Steadfast Courier Card --}}
                <div class="col-xxl-4 col-md-6 mb-4">
                    <div class="card premium-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="card-gradient" style="background: linear-gradient(135deg, #00b795 0%, #008b6f 100%);"></div>
                        <div class="card-header premium-card-header">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper me-3">
                                    <i class="fas fa-rocket fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 fw-bold pl-3">Steadfast Courier</h4>
                                    <small class="opacity-75 pl-3">Reliable & Fast Delivery</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('settings.update.api.keys') }}" method="POST" id="steadfastForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="form-floating mb-3">
                                        <label for="steadfast_api_key">
                                        <i class="fas fa-key mr-2"></i>API Key
                                    </label>
                                    <input type="text" class="form-control premium-input @error('steadfast_api_key') is-invalid @enderror" 
                                        id="steadfast_api_key" name="steadfast_api_key"
                                        placeholder="Steadfast API Key"
                                        value="{{ $steadfast->steadfast_api_key ?? '' }}" required>
                                
                                    @error('steadfast_api_key')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                     <label for="steadfast_secret_key">
                                        <i class="fas fa-lock mr-2"></i>Secret Key
                                    </label>
                                    <input type="text" class="form-control premium-input @error('steadfast_secret_key') is-invalid @enderror" 
                                        id="steadfast_secret_key" name="steadfast_secret_key"
                                        placeholder="Steadfast Secret Key"
                                        value="{{ $steadfast->steadfast_secret_key ?? '' }}" required>
                                   
                                    @error('steadfast_secret_key')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-4">
                                    <label for="settlement_charge">
                                        <i class="fas fa-dollar-sign mr-2"></i>Settlement Charge (BDT)
                                    </label>
                                    <input type="number" class="form-control premium-input @error('settlement_charge') is-invalid @enderror" 
                                        id="settlement_charge" name="settlement_charge"
                                        placeholder="Settlement Charge"
                                        step="0.01"
                                        value="{{ $steadfast->settlement_charge ?? '' }}" required>
                                    
                                    @error('settlement_charge')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn premium-btn w-100 text-white" style="background: linear-gradient(135deg, #00b795 0%, #008b6f 100%);">
                                    <i class="fas fa-save mr-2"></i> Save Configuration
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="fas fa-check-circle text-success me-1"></i> Secure Storage</span>
                                <span><i class="fas fa-shield-alt text-info me-1"></i> Encrypted</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pathao Courier Card --}}
                <div class="col-xxl-4 col-md-6 mb-4">
                    <div class="card premium-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="card-gradient" style="background: linear-gradient(135deg, #e83330 0%, #b81c1a 100%);"></div>
                        <div class="card-header premium-card-header">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper me-3">
                                    <i class="fas fa-motorcycle fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 fw-bold pl-3">Pathao Courier</h4>
                                    <small class="opacity-75 pl-3">On-Demand Delivery Service</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('settings.update.pathao') }}" method="POST" id="pathaoForm">
                                @csrf
                                @method('PUT')
                                
                                <div class="form-floating mb-3">
                                    <label for="base_url">
                                        <i class="fas fa-globe mr-2"></i>Base URL
                                    </label>
                                    <input type="url" class="form-control premium-input @error('base_url') is-invalid @enderror" 
                                        id="base_url" name="base_url"
                                        placeholder="Base URL"
                                        value="{{ $pathao->base_url ?? '' }}" required>
                                    
                                    @error('base_url')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                     <label for="store_id">
                                        <i class="fas fa-store mr-2"></i>Store ID
                                    </label>
                                    <input type="text" class="form-control premium-input @error('store_id') is-invalid @enderror" 
                                        id="store_id" name="store_id"
                                        placeholder="Store ID"
                                        value="{{ $pathao->store_id ?? '' }}">
                                   
                                    @error('store_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                      <label for="client_id">
                                        <i class="fas fa-id-card mr-2"></i>Client ID
                                    </label>
                                    <input type="text" class="form-control premium-input @error('client_id') is-invalid @enderror" 
                                        id="client_id" name="client_id"
                                        placeholder="Client ID"
                                        value="{{ $pathao->client_id ?? '' }}" required>
                                  
                                    @error('client_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <label for="client_secret">
                                        <i class="fas fa-lock mr-2"></i>Client Secret
                                    </label>
                                    <input type="text" class="form-control premium-input @error('client_secret') is-invalid @enderror" 
                                        id="client_secret" name="client_secret"
                                        placeholder="Client Secret"
                                        value="{{ $pathao->client_secret ?? '' }}" required>
                                    
                                    @error('client_secret')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                     <label for="username">
                                        <i class="fas fa-user mr-2"></i>Username
                                    </label>
                                    <input type="text" class="form-control premium-input @error('username') is-invalid @enderror" 
                                        id="username" name="username"
                                        placeholder="Username"
                                        value="{{ $pathao->username ?? '' }}" required>
                                   
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                     <label for="password">
                                        <i class="fas fa-key mr-2"></i>Password
                                    </label>
                                    <input type="text" class="form-control premium-input @error('password') is-invalid @enderror" 
                                        id="password" name="password"
                                        placeholder="Password"
                                        value="{{ $pathao->password ?? '' }}" required>
                                   
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <label for="pathao_settlement_charge">
                                        <i class="fas fa-dollar-sign mr-2"></i>Settlement Charge (BDT)
                                    </label>
                                    <input type="number" class="form-control premium-input @error('settlement_charge') is-invalid @enderror" 
                                        id="pathao_settlement_charge" name="settlement_charge"
                                        placeholder="Settlement Charge"
                                        step="0.01"
                                        value="{{ $pathao->settlement_charge ?? '' }}" required>
                                    
                                    @error('settlement_charge')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Elegant Sandbox Switch -->
                                <div class="premium-switch mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                           <label class="switch">
                                            <input type="checkbox" id="sandbox" name="sandbox"
                                                {{ isset($pathao->sandbox) && $pathao->sandbox ? 'checked' : '' }}>
                                            <span class="slider round"></span>
                                        </label>
                                        <div>
                                            <i class="fas fa-flask mr-2"></i>
                                            <span class="fw-semibold">Sandbox Mode</span>
                                            <br>
                                            <small class="text-muted">Enable test environment</small>
                                        </div>
                                     
                                    </div>
                                </div>

                                <button type="submit" class="btn premium-btn w-100 text-white" style="background: linear-gradient(135deg, #e83330 0%, #b81c1a 100%);">
                                    <i class="fas fa-save mr-2"></i> Save Configuration
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="fas fa-check-circle text-success me-1"></i> Secure Storage</span>
                                <span><i class="fas fa-flask me-1"></i> Sandbox Ready</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- BD Courier Card --}}
                <div class="col-xxl-4 col-md-6 mb-4">
                    <div class="card premium-card" data-aos="fade-up" data-aos-delay="300">
                        <div class="card-gradient" style="background: linear-gradient(135deg, #388da8 0%, #2c6e82 100%);"></div>
                        <div class="card-header premium-card-header">
                            <div class="d-flex align-items-center">
                                <div class="icon-wrapper me-3">
                                    <i class="fas fa-truck fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 fw-bold pl-3">BD Courier</h4>
                                    <small class="opacity-75 pl-3">Local Delivery Expert</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('bd-courier.create-or-update') }}" method="POST" id="bdCourierForm">
                                @csrf
                                
                                <div class="form-floating mb-4">
                                      <label for="token">
                                        <i class="fas fa-key mr-2"></i>API Token
                                    </label>
                                    <input type="text" class="form-control premium-input @error('token') is-invalid @enderror" 
                                        id="token" name="token"
                                        placeholder="BD Courier API Token"
                                        value="{{ $bd_courier->token ?? '' }}" required>
                                  
                                    @error('token')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Quick Info Card -->
                                <div class="info-card mb-4">
                                    <div class="d-flex">
                                        <div class="info-icon me-3">
                                            <i class="fas fa-info-circle fa-2x" style="color: #388da8;"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold">Quick Tip</h6>
                                            <p class="small text-muted mb-0">Your API token is used for authenticating all BD Courier API requests. Keep it secure!</p>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn premium-btn w-100 text-white" style="background: linear-gradient(135deg, #388da8 0%, #2c6e82 100%);">
                                    <i class="fas fa-save mr-2"></i> Save Token
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-footer bg-transparent border-top-0">
                            <div class="text-muted small text-center">
                                <i class="fas fa-shield-alt me-1"></i> Token is encrypted at rest
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Premium Styles */
    .premium-card {
        position: relative;
        background: white;
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.1);
    }
    
    .premium-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 45px -15px rgba(0, 0, 0, 0.2);
    }
    
    .card-gradient {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
    }
    
    .premium-card-header {
        background: transparent;
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        padding: 1.5rem;
    }
    
    .icon-wrapper {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .premium-card:hover .icon-wrapper {
        transform: scale(1.1) rotate(5deg);
    }
    
    .premium-input {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 12px 15px;
        transition: all 0.3s ease;
        font-size: 14px;
    }
    
    .premium-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-floating > label {
        padding: 1rem 0.75rem;
        font-weight: 500;
    }
    
    .premium-btn {
        border: none;
        border-radius: 12px;
        padding: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .premium-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    
    .premium-btn:active {
        transform: translateY(0);
    }
    
    /* Custom Switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: 0.4s;
        border-radius: 34px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.4s;
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: #e83330;
    }
    
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    
    .premium-switch {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
    }
    
    .info-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        padding: 15px;
        border-radius: 12px;
        border-left: 4px solid #388da8;
    }
    
    /* Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    [data-aos="fade-up"] {
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .premium-card-header {
            padding: 1rem;
        }
        
        .icon-wrapper {
            width: 40px;
            height: 40px;
        }
        
        .icon-wrapper i {
            font-size: 1.5rem !important;
        }
        
        h4 {
            font-size: 1.2rem;
        }
    }
    
    /* Loading State */
    .premium-btn.loading {
        pointer-events: none;
        opacity: 0.7;
    }
    
    .premium-btn.loading::after {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-left: -10px;
        margin-top: -10px;
        border: 2px solid white;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Form Validation Styles */
    .premium-input.is-invalid {
        border-color: #dc3545;
        background-image: none;
    }
    
    .invalid-feedback {
        font-size: 12px;
        margin-top: 5px;
    }
</style>
@endpush

@section('script')
<script>
    // Add loading state to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
            }
        });
    });
    
    // Smooth scroll to top after form submission (optional)
    if (window.location.hash === '#submitted') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
</script>
@endsection