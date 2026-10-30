@extends('backend.layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6">{{translate('WhatsApp Chat Setting')}}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('whatsapp_chat.update') }}" method="POST">
                        @csrf
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label class="col-form-label">{{translate('WhatsApp Chat')}}</label>
                            </div>
                            <div class="col-md-7">
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input value="1" name="whatsapp_chat" type="checkbox" @if (get_setting('whatsapp_chat') == 1) checked @endif>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="WHATSAPP_CHAT_NUMBER">
                            <div class="col-md-3">
                                <label class="col-form-label">{{translate('WhatsApp Chat Number')}}</label>
                            </div>
                            <div class="col-md-7">
                                <input type="text" class="form-control" name="WHATSAPP_CHAT_NUMBER" value="{{ env('WHATSAPP_CHAT_NUMBER') }}" placeholder="{{ translate('WhatsApp Chat Number') }}" required>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection