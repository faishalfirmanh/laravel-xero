const LIST_URL = 'api/get-data-product';
const CREATE_URL = 'api/save-data-product';
const URL_INVOICE = 'api/getInvoiceByIdPaket/'
const baseUrlOrigin = window.location.origin;

console.log('base url', baseUrlOrigin)
function getDataEdit(id) {
    $.ajax({
        url: `api/get-by-id/${id}`,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            let data_nya = response.Items;
            data_nya.forEach(element => {
                $("#Name").val(element.Name)
                $("#Code").val(element.Code)
                $("#Description").val(element.Description)
                $("#UnitPrice").val(element.SalesDetails.UnitPrice)
                $("#unit_price_save").val(element.SalesDetails.UnitPrice)

            });
        },
        error: function (xhr, status, error) {
            console.log('error', xhr)
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
$(document).ready(function () {


    $('#checkAll').on('change', function () {
        let isChecked = $(this).prop('checked');
        $('.invoice-checkbox').prop('checked', isChecked);
    });

    $(document).on('change', '.invoice-checkbox', function () {
        let totalCheckbox = $('.invoice-checkbox').length;
        let totalChecked = $('.invoice-checkbox:checked').length;
        $('#checkAll').prop('checked', totalCheckbox === totalChecked);
    });


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
            success: function (response) {
                console.log('ssss', response)
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
                                        <td>${contact.Description}</td>
                                        <td><button type="button" onclick="getDataEdit('${contact.ItemID}')" class="btn btn-primary">Edit</button></td>
                                    </tr>
                                `;
                        $('#contactTableBody').append(row);
                    });
                } else {
                    $('#contactTableBody').append('<tr><td colspan="6" class="text-center">Tidak ada data kontak yang ditemukan.</td></tr>');
                }
                //
                // $('#listInvoiceLoader').addClass('d-none');
            },
            error: function (xhr, status, error) {
                $('#listLoader').addClass('d-none');
                $('#contactTable').removeClass('d-none');
                console.error("Error fetching contacts:", error, status, xhr);
                $('#contactTableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data kontak dari server.</td></tr>');
            }
        });
    }

    $("#btnSaveInvoice").on('click', function (e) {
        let harga_update = $("#UnitPrice").val()
        let selectedItems = [];
        $('.invoice-checkbox:checked').each(function () {
            let checkbox = $(this);
            let data = {
                key: checkbox.val(), // Mengambil value="${key}"
                combinedInfo: checkbox.data('no-invoice'),
                amount: checkbox.data('amount')
            };
            let parts = String(data.combinedInfo).split('_');
            data.parentId = parts[0];
            data.lineItemId = parts[1];
            data.status = parts[2];
            data.no_invoice = parts[3];

            selectedItems.push(data);
        });

        if (selectedItems.length === 0) {
            alert('Harap pilih minimal satu invoice!');
            return;
        }

        // 4. Lihat Hasil di Console
        $.ajax({
            url: 'api/submitUpdateinvoices',
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify({
                price_update: harga_update,
                items: selectedItems
            }),
            success: function (response) {
                console.log(response)
            },
            error: function (xhr, err) {

            }
        })
        console.log('Data Terpilih:', selectedItems);
        console.log('harga', harga_update)
    })

    function fetchDataInvoice(idPaket) {

        $('#invoiceTable').addClass('d-none');
        const routeTemplate = "{{ route('detailInvoiceWeb', ':id') }}";
        // Bersihkan isi tbody sebelum request
        $('#invoiceTableBody').empty();

        $.ajax({
            url: `${URL_INVOICE}${idPaket}`, // Pastikan URL ini valid
            type: 'GET',
            dataType: 'json',
            success: function (response) {

                if (!response || response.length < 1) {
                    // colspan="9" disesuaikan dengan jumlah kolom header tabel Anda
                    $('#invoiceTableBody').html(`
                        <tr>
                            <td colspan="9" class="text-center">Data tidak ditemukan</td>
                        </tr>
                    `);
                    return; // Hentikan proses, jangan lanjut ke looping
                }

                let rows = '';
                let counter = 1;

                response.forEach((item, key) => {

                    // 1. Format Tanggal (Indo)
                    let date = formatDateIndo(item.tanggal);
                    let dueDate = item.tanggal_due_date ? formatDateIndo(item.tanggal_due_date) : '-';

                    // 2. Format Rupiah
                    let nominalPaid = formatRupiah(item.amount_paid);

                    // 3. Warna Status (Bootstrap Badge)
                    let statusBadge = getStatusBadge(item.status);
                    let price_afer_save = $("#unit_price_save").val();
                    let finalUrl = `${baseUrlOrigin}/detailInvoiceWeb/${item.parent_invoice_id}`;
                    let url = $(this).data('url');
                    //  console.log(finalUrl, url)
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
                                            <td>${item.total}</td>
                                            <td class="text-center">${statusBadge}</td>
                                            <td class="text-center">
                                                <a href="${finalUrl}" target="_blank" class="btn btn-primary btn-sm">
                                                    Detail
                                                </a>
                                                <div class="form-check d-flex justify-content-center">
                                                    <input class="form-check-input invoice-checkbox" 
                                                        type="checkbox" 
                                                        value="${key}" 
                                                        id="cb_${key}"
                                                        data-no-invoice="${item.parent_invoice_id}_${item.line_item_id}_${item.status}_${item.no_invoice}"
                                                        data-amount="${price_afer_save}">
                                                </div>
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
            error: function (xhr, status, error) {
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
    $('#createContactForm').on('submit', function (e) {
        e.preventDefault();

        const $submitBtn = $('#submitBtn');
        const $submitSpinner = $('#submitSpinner');
        const $formMessage = $('#formMessage');

        // Ambil data form
        const formData = {
            Code: $("#Code").val(),
            SalesDetails: {
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
            success: function (response) {
                //  console.log('payload', JSON.stringify(payload))

                // Simulasi response sukses
                $formMessage.html('<strong>Sukses!</strong> Proudct & Service berhasil disimpan.').addClass('alert-success').removeClass('d-none');
                //    $('#createContactForm')[0].reset(); // Kosongkan form
                fetchContacts(); // Muat ulang daftar kontak
                fetchDataInvoice(payload.Items[0].Code)
            },
            error: function (xhr) {
                console.log('error', xhr)
                // Tampilkan pesan error
                let errorMessage = 'Gagal menyimpan kontak. Silakan coba lagi.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $formMessage.html(`<strong>Error!</strong> ${errorMessage}`).addClass('alert-danger').removeClass('d-none');
                console.error("Error creating contact:", xhr.responseText);
            },
            complete: function () {
                // Sembunyikan loading, aktifkan tombol
                $submitBtn.prop('disabled', false);
                $submitSpinner.addClass('d-none');
            }
        });
    });
});