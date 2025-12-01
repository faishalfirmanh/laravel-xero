<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product & Services</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
        }
        .table th {
            background-color: #e9ecef;
        }
        /* Custom loader style */
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div class="container">

        <!-- CONTACT CREATION FORM -->
        <div class="card shadow mb-5">
            <div class="card-header">
               Simpan data product
            </div>
            <div class="card-body">
                <form id="createContactForm">
                      @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="Name" class="form-label">Nama Paket <span class="text-danger">*</span></label>
                            <input type="text" disabled class="form-control" id="Name" name="Name" required>
                        </div>
                         <div class="col-md-4">
                            <label for="Code" class="form-label">Code</label>
                            <input type="text" disabled class="form-control" id="Code" name="Code">
                        </div>
                        <div class="col-md-4">
                            <label for="Description" class="form-label">Penjelasan</label>
                            <input type="text" disabled class="form-control" id="Description" name="Description">
                        </div>
                        <div class="col-md-4">
                            <label for="UnitPrice" class="form-label">Harga</label>
                            <input type="text" class="form-control" id="UnitPrice" name="UnitPrice">
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary w-50" id="submitBtn">
                                Simpan Product <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                            </button>
                        </div>
                        <div class="col-12" id="notif">
                            
                        </div>
                    </div>
                </form>
                <div id="formMessage" class="mt-3 alert d-none"></div>
            </div>
        </div>

        <!-- CONTACT LIST TABLE -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                Daftar Kontak
                <button class="btn btn-light btn-sm" id="refreshBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .917-.184A6 6 0 1 1 8 2z"/>
                        <path d="M8 4.464a.5.5 0 0 1 .5.5v3.427a.5.5 0 0 1-.5.5zM8 10a.5.5 0 0 1-.5-.5V6.073a.5.5 0 0 1 1 0v3.427a.5.5 0 0 1-.5.5z"/>
                    </svg>
                    Refresh Data
                </button>
            </div>
            <div class="card-body">
                <div id="contactListContainer">
                    <div class="loader" id="listLoader"></div>
                </div>
                
                <table class="table table-striped table-bordered mt-3 d-none" id="contactTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Nama Paket</th>
                            <th>Code</th>
                            <th>Harga</th>
                             <th>Penjelasan</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="contactTableBody">
                        <!-- Data akan diisi oleh JQuery/AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-shadow">
             <div id="ListInvoiceContainer">
                <div class="loader" id="listInvoiceLoader"></div>
            </div>

                <table class="table table-striped table-bordered mt-3 d-none" id="invoiceTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th style="color: black">No Invoice</th>
                        <th style="color: black">Name Jamaah</th>
                        <th style="color: black">Name Paket</th>
                        <th style="color: black">Date</th>
                        <th style="color: black">Due Date</th>
                        <th style="color: black">Nominal Paid</th>
                        <th style="color: black">Status</th>
                        <th style="color: black">Action Pilih</th>
                    </tr>
                </thead>
                <tbody id="invoiceTableBody">
        
                </tbody>
            </table>
        </div>

    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JQuery and AJAX Logic -->
    <script>
         // URL endpoint sesuai dengan Lumen route yang telah diperbaiki
            const LIST_URL = 'api/get-data-product'; 
            const CREATE_URL = 'api/save-data-product'; 
            const URL_INVOICE = 'api/getInvoiceByIdPaket/'
         function getDataEdit(id){
            $.ajax({
                url: `api/get-by-id/${id}`,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                  let data_nya = response.Items;
                  data_nya.forEach(element => {
                    $("#Name").val(element.Name)
                    $("#Code").val(element.Code)
                    $("#Description").val(element.Description)
                    $("#UnitPrice").val(element.SalesDetails.UnitPrice)
                      
                  });
                },
                error: function(xhr, status, error) {
                    console.log('error',xhr)
                }
            });
        }


        function formatDateIndo(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric'
            }).format(date);
        }

        // Helper: Format Rupiah
        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(amount);
        }

        // Helper: Badge Warna Status
        function getStatusBadge(status) {
            let color = 'secondary';
            if (status === 'PAID') color = 'success';
            else if (status === 'AUTHORISED') color = 'primary'; // Biru untuk Authorised (Open)
            else if (status === 'DRAFT') color = 'warning';
            else if (status === 'VOIDED') color = 'danger';
            
            return `<span class="badge bg-${color}">${status}</span>`;
        }

        function formatRupiah(angka) {
            const number = Number(angka) || 0; 
            return new Intl.NumberFormat('id-ID', { 
                style: 'currency', 
                currency: 'IDR',
                minimumFractionDigits: 0, // Ubah jadi 2 jika ingin ada sen (,00)
                maximumFractionDigits: 0 
            }).format(number);
        }
        $(document).ready(function() {
         
            /**
             * Fungsi untuk mengambil dan menampilkan daftar kontak.
             */
            function fetchContacts() {
                $('#listLoader').removeClass('d-none');
                $('#contactTable').addClass('d-none');
                $('#contactTableBody').empty();


                // $.ajax({
                //     url: `api/get-invoices`,
                //     type: 'GET',
                //     dataType: 'json',
                //     success: function(data, textStatus, xhr) {
                //          if(xhr.status == 'success'){
                            
                //             $('#notif').
                //             html('<div class="alert alert-primary" role="alert">data tersingkronisasi</div>');
                //          }
                        
                    
                //     },
                //     error: function(xhr, status, error) {
                //         console.log('error',xhr)
                //     }
                // });

                $.ajax({
                    url: LIST_URL,
                    type: 'GET',
                    dataType: 'json',
                    // Gunakan mode success/error untuk simulasi,
                    // karena ini hanya frontend, kita akan gunakan data dummy response
                    success: function(response) {
                        console.log('ssss',response)
                        $('#listLoader').addClass('d-none');
                        $('#contactTable').removeClass('d-none');
                    
                        
                        const contacts = response.Items;// || dummyResponse.Contacts;
                        
                        if (contacts && contacts.length > 0) {
                            let counter = 1;
                            contacts.forEach(contact => {
                                // Cari nomor telepon DEFAULT
                                const defaultPhone = contact.Phones ? contact.Phones.find(p => p.PhoneType === 'DEFAULT') : null;
                                const phoneNumber = defaultPhone && defaultPhone.PhoneNumber ? `${defaultPhone.PhoneNumber}` : '-';

                                // Tentukan badge status
                                const statusClass = contact.ContactStatus === 'ACTIVE' ? 'bg-success' : 'bg-secondary';
                                const statusBadge = `<span class="badge ${statusClass}">${contact.ContactStatus}</span>`;

                                // Buat baris tabel
                                const row = `
                                    <tr>
                                        <td>${counter++}</td>
                                        <td>${contact.Name || '-'}</td>
                                        <td>${contact.Code || '-'}</td>
                                        <td>Rp. ${formatRupiah(contact.SalesDetails.UnitPrice)}</td>
                                        <td>${contact.Description }</td>
                                        <td><button type="button" onclick="getDataEdit('${contact.ItemID}')" class="btn btn-primary">Edit</button></td>
                                    </tr>
                                `;
                                $('#contactTableBody').append(row);
                            });
                        } else {
                            $('#contactTableBody').append('<tr><td colspan="6" class="text-center">Tidak ada data kontak yang ditemukan.</td></tr>');
                        }
                          //
                    },
                    error: function(xhr, status, error) {
                        $('#listLoader').addClass('d-none');
                        $('#contactTable').removeClass('d-none');
                        console.error("Error fetching contacts:", error,status, xhr);
                        $('#contactTableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data kontak dari server.</td></tr>');
                    }
                });
            }

                    function fetchDataInvoice(idPaket) {
                         // Tampilkan loader jika ada, sembunyikan tabel dulu
                        $('#listInvoiceLoader').removeClass('d-none');
                        $('#invoiceTable').addClass('d-none');
                        
                        // Bersihkan isi tbody sebelum request
                        $('#invoiceTableBody').empty();

                        $.ajax({
                            url: `${URL_INVOICE}${idPaket}`, // Pastikan URL ini valid
                            type: 'GET',
                            dataType: 'json',
                            success: function(response) {
                                console.log('get invoice', response);
                                
                                // Sembunyikan loader
                              
                                
                                // Cek apakah response kosong
                                if (!response || Object.keys(response).length === 0) {
                                    $('#invoiceTableBody').html('<tr><td colspan="8" class="text-center">Data tidak ditemukan</td></tr>');
                                    $('#invoiceTable').removeClass('d-none');
                                    return;
                                }

                                let rows = '';
                                let counter = 1;

                                // KARENA DATA BERUPA OBJECT, GUNAKAN Object.entries()
                                // key = UUID (misal: "b98a571d..."), item = Data Object
                                Object.entries(response).forEach(([key, item]) => {
                                    
                                    // 1. Format Tanggal (Indo)
                                    let date = formatDateIndo(item.tanggal);
                                    let dueDate = item.tanggal_due_date ? formatDateIndo(item.tanggal_due_date) : '-';

                                    // 2. Format Rupiah
                                    let nominalPaid = formatRupiah(item.amount_paid);

                                    // 3. Warna Status (Bootstrap Badge)
                                    let statusBadge = getStatusBadge(item.status);

                                    // 4. Susun HTML Row
                                    rows += `
                                        <tr>
                                            <td>${counter++}</td>
                                            <td>${item.no_invoice}</td>
                                            <td>${item.paket_name}</td>
                                            <td>${item.nama_jamaah || '-'}</td>
                                            <td>${date}</td>
                                            <td>${dueDate}</td>
                                            <td class="text-end">${nominalPaid}</td>
                                            <td class="text-center">${statusBadge}</td>
                                            <td class="text-center">
                                                <button type="button" 
                                                        class="btn btn-primary btn-sm" 
                                                        onclick="pilihInvoice('${key}', '${item.no_invoice}', '${item.amount_paid}')">
                                                    <i class="fas fa-check"></i> Pilih
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });

                                // Masukkan rows ke tbody
                                $('#invoiceTableBody').html(rows);
                                // Munculkan tabel
                                $('#listInvoiceLoader').addClass('d-none');
                                $('#invoiceTable').removeClass('d-none');
                            },
                            error: function(xhr, status, error) {
                                $('#listInvoiceLoader').addClass('d-none');
                                $('#invoiceTable').removeClass('d-none');
                                console.error("Error fetching data:", error, status, xhr);
                                
                                // Perbaikan: Target ke tbody, jangan ke table ID agar header tidak hilang
                                $('#invoiceTableBody').html('<tr><td colspan="8" class="text-center text-danger">Gagal mengambil data invoice dari server.</td></tr>');
                            }
                        });
                    }
           

            // Panggil fungsi saat halaman pertama kali dimuat
            fetchContacts();

            // Event listener untuk tombol refresh
            $('#refreshBtn').on('click', fetchContacts);

            // Event listener untuk pengiriman form
            $('#createContactForm').on('submit', function(e) {
                e.preventDefault();

                const $submitBtn = $('#submitBtn');
                const $submitSpinner = $('#submitSpinner');
                const $formMessage = $('#formMessage');
                
                // Ambil data form
                const formData = {
                  	Code: $("#Code").val(),
                    SalesDetails :{
                         UnitPrice: $("#UnitPrice").val()
                    }
                };

                // Format data sesuai permintaan JSON (nested structure)
                const payload = {
                    "Items": [formData]
                };

                // Tampilkan loading, nonaktifkan tombol
                $submitBtn.prop('disabled', true);
                $submitSpinner.removeClass('d-none');
                $formMessage.addClass('d-none').removeClass('alert-success alert-danger');
 
 
               


                $.ajax({
                    url: CREATE_URL,
                    type: 'POST',
                    contentType: 'application/json', 
                    data: JSON.stringify(payload),
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                    },
                    success: function(response) {
                        console.log('payload',JSON.stringify(payload))
                    
                        // Simulasi response sukses
                        $formMessage.html('<strong>Sukses!</strong> Proudct & Service berhasil disimpan.').addClass('alert-success').removeClass('d-none');
                    //    $('#createContactForm')[0].reset(); // Kosongkan form
                        fetchContacts(); // Muat ulang daftar kontak
                        fetchDataInvoice(payload.Items[0].Code)
                    },
                    error: function(xhr) {
                        console.log('error',xhr)
                        // Tampilkan pesan error
                        let errorMessage = 'Gagal menyimpan kontak. Silakan coba lagi.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        $formMessage.html(`<strong>Error!</strong> ${errorMessage}`).addClass('alert-danger').removeClass('d-none');
                        console.error("Error creating contact:", xhr.responseText);
                    },
                    complete: function() {
                        // Sembunyikan loading, aktifkan tombol
                        $submitBtn.prop('disabled', false);
                        $submitSpinner.addClass('d-none');
                    }
                });
            });
        });
    </script>

</body>
</html>