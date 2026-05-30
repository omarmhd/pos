@extends('layouts.app')

@section('page-title', 'إضافة فاتورة شراء')

@section('content')
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-bag-plus"></i> إضافة فاتورة شراء جديدة</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('purchases.store') }}" method="POST" id="purchaseForm">
                @csrf
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">المورد <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                            <option value="">اختر المورد</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }} - {{ $supplier->company }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">حالة الدفع <span class="text-danger">*</span></label>
                        <select name="payment_status" class="form-select" id="paymentStatus" required>
                            <option value="paid">مدفوع بالكامل</option>
                            <option value="partial">مدفوع جزئياً</option>
                            <option value="unpaid">غير مدفوع</option>
                        </select>
                    </div>
                </div>

                <!-- Products Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">المنتجات</h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addProductRow()">
                            <i class="bi bi-plus-circle"></i> إضافة منتج
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="productsTable">
                                <thead>
                                <tr>
                                    <th style="width: 40%">المنتج</th>
                                    <th style="width: 15%">الكمية</th>
                                    <th style="width: 20%">سعر الوحدة</th>
                                    <th style="width: 20%">المجموع</th>
                                    <th style="width: 5%"></th>
                                </tr>
                                </thead>
                                <tbody id="productRows">
                                <!-- Product rows will be added here -->
                                </tbody>
                                <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>الإجمالي:</strong></td>
                                    <td colspan="2"><strong id="totalAmount">0.00 {{ $currency }}</strong></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">المبلغ المدفوع <span class="text-danger">*</span></label>
                        <input type="number" name="paid_amount" class="form-control"
                               id="paidAmount" value="0" step="0.01" min="0" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">المبلغ المتبقي</label>
                        <input type="text" class="form-control" id="remainingAmount" value="0.00 {{ $currency }}" readonly>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-save"></i> حفظ الفاتورة
                </button>
                <a href="{{ route('purchases.index') }}" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> إلغاء
                </a>
            </form>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        const products = @json($products);
        let rowCount = 0;

        function addProductRow() {
            rowCount++;
            const row = `
        <tr id="row${rowCount}">
            <td>
                <select name="items[${rowCount}][product_id]" class="form-select product-select"
                        onchange="updateProductPrice(${rowCount})" required>
                    <option value="">اختر المنتج</option>
                    ${products.map(p => `<option value="${p.id}" data-price="${p.cost_price}">${p.name} (${p.barcode})</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][quantity]" class="form-control quantity-input"
                       value="1" min="1" onchange="calculateRow(${rowCount})" required>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][unit_price]" class="form-control unit-price"
                       step="0.01" min="0" onchange="calculateRow(${rowCount})" required>
            </td>
            <td>
                <input type="text" class="form-control row-total" readonly value="0.00">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${rowCount})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `;
            $('#productRows').append(row);
        }

        function updateProductPrice(rowId) {
            const select = $(`#row${rowId} .product-select`);
            const selectedOption = select.find('option:selected');
            const price = selectedOption.data('price') || 0;
            $(`#row${rowId} .unit-price`).val(price);
            calculateRow(rowId);
        }

        function calculateRow(rowId) {
            const quantity = parseFloat($(`#row${rowId} .quantity-input`).val()) || 0;
            const unitPrice = parseFloat($(`#row${rowId} .unit-price`).val()) || 0;
            const total = quantity * unitPrice;
            $(`#row${rowId} .row-total`).val(total.toFixed(2));
            calculateTotal();
        }

        function removeRow(rowId) {
            $(`#row${rowId}`).remove();
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            $('.row-total').each(function() {
                const value = parseFloat($(this).val()) || 0;
                total += value;
            });
            $('#totalAmount').text(total.toFixed(2) + ' {{ $currency }}');
            updateRemaining();
        }

        function updateRemaining() {
            const total = parseFloat($('#totalAmount').text()) || 0;
            const paid = parseFloat($('#paidAmount').val()) || 0;
            const remaining = total - paid;
            $('#remainingAmount').val(remaining.toFixed(2) + ' {{ $currency }}');
        }

        $('#paidAmount').on('input', updateRemaining);

        $('#paymentStatus').on('change', function() {
            const total = parseFloat($('#totalAmount').text()) || 0;
            const status = $(this).val();

            if (status === 'paid') {
                $('#paidAmount').val(total.toFixed(2));
            } else if (status === 'unpaid') {
                $('#paidAmount').val('0');
            }
            updateRemaining();
        });

        // Add first row on load
        $(document).ready(function() {
            addProductRow();
        });

        // Validate form
        $('#purchaseForm').on('submit', function(e) {
            if ($('#productRows tr').length === 0) {
                e.preventDefault();
                alert('يجب إضافة منتج واحد على الأقل!');
                return false;
            }
        });
    </script>
@endsection
