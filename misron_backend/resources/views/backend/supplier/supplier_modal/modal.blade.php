<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{ route('supplier.store') }}" method="POST">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="business_name">Business Name:</label>
                            <input type="text" name="business_name" class="form-control mb-2" id="business_name">
                        </div>
                        <div class="form-group col-md-6 d-none">
                            <label for="contact_id">Contact ID:</label>
                            <input type="text" name="contact_id" class="form-control mb-2" id="contact_id" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="mobile">Mobile:</label>
                            <input type="number" name="mobile" class="form-control mb-2" id="mobile">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="opening_balance">Opening Balance:</label>

                            <div class="d-flex align-items-center">
                                <input type="number" name="opening_balance" class="form-control mb-2" value="0"
                                    id="opening_balance">

                                <div class="form-check ml-3 mb-2">
                                    <input class="form-check-input" type="checkbox" name="balance_type" value="due"
                                        id="due_checkbox">

                                    <label class="form-check-label" for="due_checkbox">
                                        Due
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-12">
                            <label for="address">Address:</label>
                            <input type="text" name="address" class="form-control mb-2" id="address">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="contact_person_name">Contact Person Name:</label>
                            <input type="text" name="contact_person_name" class="form-control mb-2"
                                id="contact_person_name">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="email">Email:</label>
                            <input type="email" name="email" class="form-control mb-2" id="email">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="tax_number">Tax Number:</label>
                            <input type="text" name="tax_number" class="form-control mb-2" id="tax_number">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pay_term">Pay Term:</label>
                            <select name="pay_term" class="form-control mb-2" id="pay_term">
                                <option value="cash">Cash</option>
                                <option value="bill_to_bill">Bill To Bill</option>
                                <option value="days">Days</option>
                                <option value="monthly">Monthly</option>
                                <option value="after_sales">After Sales</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
