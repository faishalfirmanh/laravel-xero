
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xero Clone Invoice Table</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body { background-color: #f6f7f8; padding: 20px; }
        .card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-invoice th { background-color: #f1f3f5; font-size: 13px; font-weight: 600; border-top: none; }
        .table-invoice td { vertical-align: middle; padding: 5px; }
        .table-invoice .form-control { border: 1px solid #dee2e6; font-size: 13px; height: 38px; }
        .table-invoice .form-control:focus { border-color: #00b0ff; box-shadow: none; }
        .btn-add-row { font-size: 13px; font-weight: 600; color: #007bff; background: none; border: none; padding: 10px 0; }
        .totals-section { margin-top: 20px; border-top: 2px solid #ddd; padding-top: 10px; }
        .total-row { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="card p-4">
        <div class="d-flex justify-content-between mb-4">
            <h4>Edit Invoice: <span id="headerInvoiceNo">...</span> <span id="status_header"></span> </h4>
            <button class="btn btn-primary btn-sm" onclick="fetchDataDummy()">
                <i class="fas fa-sync"></i> Load Data from API
            </button>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <label>To</label>
                <input type="text" id="contactName" class="form-control" value="">
            </div>
            <div class="col-md-2">
                <label>Date</label>
                <input type="date" id="invoiceDate" class="form-control" value="">
            </div>
            <div class="col-md-2">
                <label>Due Date</label>
                <input type="date" id="dueDate" class="form-control" value="">
            </div>
            <div class="col-md-2">
                <label>Invoice #</label>
                <input type="text" id="invoiceNumber" class="form-control" value="">
            </div>
        </div>

        <form id="invoiceForm">
            <div class="table-responsive">
                <table class="table table-bordered table-invoice" id="invoiceTable">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Item</th>
                            <th>Description</th>
                            <th style="width: 80px;">Qty <span class="text-danger">*</span></th>
                            <th style="width: 130px;">Price</th>
                            <th style="width: 80px;">Disc (Rp)</th>
                            <th style="width: 150px;">Account</th>
                            <th style="width: 120px;">Tax Rate</th>
                            <th style="width: 100px;">Tax Amt</th>
                            <th style="width: 120px;">Agen</th>
                            <th style="width: 100px;">Divisi</th>
                            <th style="width: 150px;">Amount IDR</th>
                            <th style="width: 50px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <tr>
                            <td><select class="form-control item-select"><option>Select Item</option></select></td>
                            <td><input type="text" disabled class="form-control"></td>
                            <td><input type="number" class="form-control qty" min="1" value="1"></td>
                            <td><input type="number" disabled class="form-control price" value="0"></td>
                            <td><input type="number" class="form-control disc" value="0"></td>
                            <td><select class="form-control account" disabled><option>Select</option></select></td>
                            <td><select class="form-control tax-rate"><option value="0">0%</option></select></td>
                            <td><input type="number" class="form-control tax-amount" readonly value="0"></td>
                            <td><select class="form-control"><option>None</option></select></td>
                            <td><select class="form-control"><option>None</option></select></td>
                            <td><input type="text" class="form-control amount" readonly value="0"></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <button type="button" class="btn-add-row" id="addRowBtn">
                        <i class="fas fa-plus"></i> Add a new line
                    </button>
                    <br>
                    <button type="submit" class="btn btn-success mt-3">Save Invoice</button>
                </div>
                <div class="col-md-6 text-right totals-section">
                    <div class="row">
                        <div class="col-8">Subtotal</div>
                        <div class="col-4" id="subTotalDisplay">0.00</div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-8">Total Tax</div>
                        <div class="col-4" id="taxTotalDisplay">0.00</div>
                    </div>
                    <div class="row mt-3 total-row">
                        <div class="col-8">Total IDR</div>
                        <div class="col-4" id="grandTotalDisplay">0.00</div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
{{-- <script src="{{ asset('assets/js/detail_invoices.js?v.12') }}"></script> --}}

<script>
// --- DATA DUMMY (Disimpan diluar agar bisa dipanggil fungsi) ---

let URL_API_DETAIL = '	https://api.xero.com/api.xro/2.0/Invoices/'
 let fullUrl = window.location.href;
    let code_invoice = fullUrl.split('/').pop();
 //   console.log(code_invoice)
    let origin_url = new URL(window.location.origin);
    var BASE_URL = "{{ url('/') }}";

let list_agent = [];

let list_divisi = [];

let availableProducts = [];
function getAllitems(){
     $.ajax({
            url: `${BASE_URL}/api/get-data-product`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
             
               if(response.Items){
                availableProducts = response.Items; 
               }
            },
            error: function (xhr, status, error) {
                console.log("xxxxx",xhr)
            }
    });
}


function getDevisi(){
     $.ajax({
            url: `${BASE_URL}/api/get_divisi`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                list_divisi = response[0]; 
                //console.log("devisi", response[0])
            },
            error: function (xhr, status, error) {
                console.log("xxxxx",xhr)
            }
    });
}



function getAgent(){
     $.ajax({
            url: `${BASE_URL}/api/get_agent`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
              list_agent = response[0];
            },
            error: function (xhr, status, error) {
                console.log("xxxxx",xhr)
            }
    });
}

let list_account = [];

function getDataAccount(){
     $.ajax({
            url: `${BASE_URL}/api/getAllAccount`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                list_account = response.GroupedAccounts;
              //console.log("Ress account0", response)
            },
            error: function (xhr, status, error) {
                console.log("xxxxx",xhr)
            }
    });
}


$(document).ready(function() {
    getAllitems();
    getAgent();
    getDevisi();
    getDataAccount();
})



function getStatusBadge(status) {
    let color = 'secondary'; // Default color (abu-abu)

    let statusUpper = status ? status.toUpperCase() : 'UNKNOWN';

    if (statusUpper === 'PAID') color = 'success';
    else if (statusUpper === 'AUTHORISED') color = 'primary';
    else if (statusUpper === 'DRAFT') color = 'warning';
    else if (statusUpper === 'VOIDED') color = 'danger';

    $("#status_header")
        .removeClass() // Hapus semua class sebelumnya
        .addClass(`badge bg-${color}`) // Tambah class baru
        .text(statusUpper); // Set teks
}

// ... AJAX SCIRPT ...


// --- FIX 3: DEFINISIKAN FUNGSI fetch DI GLOBAL SCOPE ---
function fetchDataDummy() {
    // Simulasi memanggil data dan memasukkannya ke form
   
    console.log('code',code_invoice)
    let urlTarget = `${BASE_URL}/api/getDetailInvoice/${code_invoice}`;
      $.ajax({
            url: urlTarget,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                console.log(response.Invoices[0].Status)
                getStatusBadge(response.Invoices[0].Status);
                let data_baris = response.Invoices[0];
                loadInvoiceToForm(data_baris);
                getAllitems();
            },
            error: function (xhr, status, error) {
                console.error('errro',xhr)
            }
        });

  
}

$(document).on('change', '.item-select', function() {
    // 1. Tangkap elemen yang berubah
    let self = $(this);
    let itemCode = self.val();
    let currentRow = self.closest('tr'); // Baris spesifik tempat select berada

    if (!itemCode) {
        currentRow.find('.price').val(0);
        currentRow.find('.description').val('');
        currentRow.find('.amount').val(0);
        calculateTotal(); // Recalculate totals
        return;
    }

    // 3. Panggil API Detail Product
    let urlProduct = `${BASE_URL}/api/get-by-id/${itemCode}`;
    
    $.ajax({
        url: urlProduct,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log("Response Product:", response);
            
            if (response.Items && response.Items.length > 0) {
                let itemData = response.Items[0];
                let salesDetails = itemData.SalesDetails;

                // 4. Update Field di Baris Tersebut
                // .val() untuk input value, pastikan class selector benar
                
                // Set Price
                currentRow.find('.price').val(salesDetails.UnitPrice || 0);
                
                // Set Description
                currentRow.find('.description').val(itemData.Description || itemData.Name);
                
                // Set Account (Jika ada dropdown account)
                if(salesDetails.AccountCode) {
                    currentRow.find('.account').val(salesDetails.AccountCode);
                }

                // 5. Trigger Kalkulasi Ulang (PENTING)
                // Kita trigger event 'input' pada price agar fungsi calculateTotal() jalan
                currentRow.find('.price').trigger('input'); 
            }
        },
        error: function(xhr, status, error) {
            console.error("Gagal ambil detail product:", error);
            currentRow.find('.description').val('Error fetching data');
        }
    });
});

// --- FUNGSI GENERATE HTML ---
function generateRowHtml(item = null) {
    const isNew = item === null;
    const itemCode = isNew ? "" : (item.ItemCode || "");
    const desc = isNew ? "" : (item.Description || "");
    const qty = isNew ? 1 : (item.Quantity || 0);
    const price = isNew ? 0 : (item.UnitAmount || 0);
    const accCode = isNew ? "200" : (item.AccountCode || "200");
    const taxAmt = isNew ? 0 : (item.TaxAmount || 0);
    const lineAmt = isNew ? 0 : (item.LineAmount || 0);
    const taxRateVal = (taxAmt > 0) ? "11" : "0";
   


    //account-----
    let items_option_account = `<option value="">Select Acount</option>`;
    list_account.forEach(account => {
        //console.log(account)
        const a_code = account.Code; 
        const a_name = `${account.Code}-${account.Name}-${account.Class}`; 
        const a_selected = (a_code == accCode) ? 'selected' : '';
        items_option_account += `<option value="${a_code}" ${a_selected}>${a_name}</option>`;
    });
    //------------

    //console.log("account",list_account)
   
    // --- NEW LOGIC: Generate Item Options dynamically ---
    let itemOptionsHtml = `<option value="">Select Item</option>`;
    availableProducts.forEach(product => {
        const pCode = product.Code; 
        const pName = product.Name; 
        const isSelected = (itemCode == pCode) ? 'selected' : '';
        itemOptionsHtml += `<option value="${pCode}" ${isSelected}>${pName}</option>`;
    });


    let itemOptionsAgent = `<option value="">Select Agent</option>`;
    let itemOptionsDevisi = `<option value="">Select Devisi</option>`;
    if(item != null){
        if( item.Tracking.length > 0){
            item.Tracking.forEach((x,y)=>{
                if(x.Name == "Agent"){   
                    list_agent.forEach(ag => {
                        const code_agent = ag.TrackingOptionID; 
                        const name_agent = ag.Name; 
                        //console.log("name agent ",name_agent)
                        const isSelected_agent = (x.TrackingOptionID == code_agent) ? 'selected' : '';
                        itemOptionsAgent += `<option value="${code_agent}" ${isSelected_agent}>${name_agent}</option>`;
                    });
                }else if(x.Name == "Divisi"){
                    list_divisi.forEach(dev => {
                        const code_devisi = dev.TrackingOptionID; 
                        const name_devisi = dev.Name; 
                        //console.log("name divisi ",name_devisi)
                        const isSelected_devisi = (x.TrackingOptionID == code_devisi) ? 'selected' : '';
                        itemOptionsDevisi += `<option value="${code_devisi}" ${isSelected_devisi}>${name_devisi}</option>`;
                    });
                }
            })
        }
    }
   
    // ----------------------------------------------------

    return `
        <tr>
            <td>
                <select class="form-control item-select">
                    ${itemOptionsHtml}  
                </select>
            </td>
            <td><input type="text" disabled class="form-control description" value="${desc}"></td>
            <td><input type="number" class="form-control qty" value="${qty}" required></td>
            <td><input type="number" disabled class="form-control price" value="${price}"></td>
            <td><input type="number" class="form-control disc" value="0" placeholder="0"></td>
            <td>
                <select class="form-control account">
                    ${items_option_account}
                </select>
            </td>
            <td>
                <select class="form-control tax-rate">
                    <option value="0" ${taxRateVal == '0' ? 'selected' : ''}>Tax Exempt (0%)</option>
                    <option value="11" ${taxRateVal == '11' ? 'selected' : ''}>PPN (11%)</option>
                </select>
            </td>
            <td><input type="number" class="form-control tax-amount" value="${taxAmt}" readonly></td>
            
            <td>
                <select class="form-control agent">
                   ${itemOptionsAgent}
                </select>
            </td>
            <td>
                <select class="form-control devisi">
                   ${itemOptionsDevisi}
                </select>
            </td>

            <td><input type="text" class="form-control amount" disabled readonly value="${lineAmt}"></td>
            
            <td style="min-width: 90px; vertical-align: middle;">
                <div class="d-flex justify-content-center align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row mr-2" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-success save-row" title="Simpan">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </td>
        </tr>
    `;
}

// --- FUNGSI LOAD DATA KE FORM ---
function loadInvoiceToForm(invoiceData) {
    // 1. Set Header (Sekarang ID nya sudah ada di HTML)
    $('#contactName').val(invoiceData.Contact.Name);
    $('#invoiceNumber').val(invoiceData.InvoiceNumber);
    $('#headerInvoiceNo').text(invoiceData.InvoiceNumber);

    // 2. Format Tanggal
    if (invoiceData.DateString) $('#invoiceDate').val(invoiceData.DateString.split('T')[0]);
    if (invoiceData.DueDateString) $('#dueDate').val(invoiceData.DueDateString.split('T')[0]);

    // 3. Render Tabel (Sekarang TBODY ID nya sudah ada)
    const tbody = $('#invoiceTableBody');
    tbody.empty();

    if (invoiceData.LineItems && invoiceData.LineItems.length > 0) {
        invoiceData.LineItems.forEach(item => {
            tbody.append(generateRowHtml(item));
        });
    }
    calculateTotal();
}

// --- HITUNG TOTAL ---
function calculateTotal() {
    let grandTotal = 0;
    let totalTax = 0;

    $('#invoiceTableBody tr').each(function () {
        let amt = parseFloat($(this).find('.amount').val()) || 0;
        let tax = parseFloat($(this).find('.tax-amount').val()) || 0;
        grandTotal += amt;
        totalTax += tax;
    });

    $('#taxTotalDisplay').text(totalTax.toLocaleString('id-ID'));
    $('#grandTotalDisplay').text(grandTotal.toLocaleString('id-ID'));
    $('#subTotalDisplay').text((grandTotal - totalTax).toLocaleString('id-ID'));
}

// --- EVENT LISTENERS ---
$(document).ready(function () {
    // Tombol Add Row
    $('#addRowBtn').click(function () {
        $('#invoiceTableBody').append(generateRowHtml(null));
    });

    // Tombol Hapus Row
    $(document).on('click', '.remove-row', function () {
        $(this).closest('tr').remove();
        calculateTotal();
    });

    // Kalkulasi Otomatis
    $(document).on('input change', '.qty, .price, .disc, .tax-rate', function () {
        let row = $(this).closest('tr');
        let qty = parseFloat(row.find('.qty').val()) || 0;
        let price = parseFloat(row.find('.price').val()) || 0;
        let disc = parseFloat(row.find('.disc').val()) || 0;
        let taxRate = parseFloat(row.find('.tax-rate').val()) || 0;

        let subtotal = qty * price;
        let afterDisc = subtotal - ((subtotal * disc) / 100);
        let taxAmt = (afterDisc * taxRate) / 100;

        row.find('.tax-amount').val(taxAmt.toFixed(2));
        row.find('.amount').val((afterDisc + taxAmt).toFixed(2));

        calculateTotal();
    });
}); 
</script>

</body>
</html>
```