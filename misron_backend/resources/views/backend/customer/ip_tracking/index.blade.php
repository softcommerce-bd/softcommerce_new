@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
        <h1 class="h3">{{translate('IP Tracking & Blocking')}}</h1>
    </div>
</div>

<div class="card">
    <form class="" id="sort_ips" action="" method="GET">
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-0 h6">{{translate('Tracked IPs')}}</h5>
            </div>

            <div class="dropdown mb-2 mb-md-0">
                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                    {{translate('Bulk Action')}}
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item confirm-alert" href="javascript:void(0)" data-target="#bulk-delete-modal">{{translate('Delete selection')}}</a>
                </div>
            </div>

            <div class="col-md-2">
                <div class="form-group mb-0">
                    <select class="form-control aiz-select-browser" name="status" id="status_filter" onchange="sort_customers(this)">
                        <option value="">{{translate('All Status')}}</option>
                        <option value="active" {{ $sort_status == 'active' ? 'selected' : '' }}>{{translate('Active')}}</option>
                        <option value="blocked" {{ $sort_status == 'blocked' ? 'selected' : '' }}>{{translate('Blocked')}}</option>
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search" @isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type IP, name, phone or email & Enter') }}">
                </div>
            </div>
        </div>

        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th>
                            <div class="form-group">
                                <div class="aiz-checkbox-inline">
                                    <label class="aiz-checkbox">
                                        <input type="checkbox" class="check-all">
                                        <span class="aiz-square-check"></span>
                                    </label>
                                </div>
                            </div>
                        </th>
                        <th>{{translate('IP Address')}}</th>
                        <th data-breakpoints="lg">{{translate('Customer Name')}}</th>
                        <th data-breakpoints="lg">{{translate('Phone')}}</th>
                        <th data-breakpoints="lg">{{translate('Email')}}</th>
                        <th>{{translate('Status')}}</th>
                        <th data-breakpoints="lg">{{translate('Blocked At')}}</th>
                        <th data-breakpoints="lg">{{translate('Blocked By')}}</th>
                        <th class="text-right">{{translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ips as $key => $ip)
                        <tr>
                            <td>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-one" name="id[]" value="{{$ip->id}}">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong>{{$ip->ip_address}}</strong>
                            </td>
                            <td>{{$ip->customer_name ?? '-'}}</td>
                            <td>{{$ip->customer_phone ?? '-'}}</td>
                            <td>{{$ip->customer_email ?? '-'}}</td>
                            <td>
                                @if($ip->status == 'blocked')
                                    <span class="badge badge-inline danger">{{translate('Blocked')}}</span>
                                @else
                                    <span class="badge badge-inline success">{{translate('Active')}}</span>
                                @endif
                            </td>
                            <td>{{$ip->blocked_at ? $ip->blocked_at->format('d M Y, h:i A') : '-'}}</td>
                            <td>{{$ip->blockedBy ? $ip->blockedBy->name : '-'}}</td>
                            <td class="text-right">
                                @if($ip->status == 'blocked')
                                    <a href="#" class="btn btn-soft-success btn-icon btn-circle btn-sm" onclick="confirm_toggle('{{route('ip-tracking.toggle-block', encrypt($ip->id))}}', '{{ translate('Do you really want to unblock this IP?') }}');" title="{{ translate('Unblock IP') }}">
                                        <i class="las la-unlock"></i>
                                    </a>
                                @else
                                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm" onclick="confirm_toggle('{{route('ip-tracking.toggle-block', encrypt($ip->id))}}', '{{ translate('Do you really want to block this IP?') }}');" title="{{ translate('Block IP') }}">
                                        <i class="las la-ban"></i>
                                    </a>
                                @endif
                                <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('ip-tracking.destroy', $ip->id)}}" title="{{ translate('Delete') }}">
                                    <i class="las la-trash"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="aiz-pagination">
                {{ $ips->appends(request()->input())->links() }}
            </div>
        </div>
    </form>
</div>

<!-- Toggle Block Confirmation Modal -->
<div class="modal fade" id="confirm-toggle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title h6">{{translate('Confirmation')}}</h5>
                <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="toggle-message">{{translate('Do you really want to toggle block status?')}}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Cancel')}}</button>
                <a type="button" id="toggle-confirmation" class="btn btn-primary">{{translate('Proceed!')}}</a>
            </div>
        </div>
    </div>
</div>

@endsection

@section('modal')
    @include('modals.delete_modal')
    @include('modals.bulk_delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if(this.checked) {
                $('.check-one:checkbox').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check-one:checkbox').each(function() {
                    this.checked = false;
                });
            }
        });

        function sort_customers(el){
            $('#sort_ips').submit();
        }

        function confirm_toggle(url, message)
        {
            if('{{env('DEMO_MODE')}}' == 'On'){
                AIZ.plugins.notify('info', '{{ translate('Data can not change in demo mode.') }}');
                return;
            }

            $('#toggle-message').text(message);
            $('#confirm-toggle').modal('show', {backdrop: 'static'});
            document.getElementById('toggle-confirmation').setAttribute('href', url);
        }

        function bulk_delete() {
            var data = new FormData($('#sort_ips')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('ip-tracking.bulk-delete')}}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if(response == 1) {
                        location.reload();
                    }
                }
            });
        }
    </script>
@endsection
