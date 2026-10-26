/**
 * Helper format angka/uang yang dipakai bersama modul POS.
 * Nilainya harus konsisten dengan helper di app/Support/helpers.php.
 */

export function tampilkanKilogramJs(value) {
    const n = Number(value) || 0;
    if (n === Math.trunc(n)) return String(Math.trunc(n));
    return String(Math.round(n * 100) / 100).replace('.', ',');
}

export function tampilkanRupiahJs(value) {
    return 'Rp' + Math.round(parseFloat(value) || 0).toLocaleString('id-ID');
}

/** Angka jumlah/kg: koma desimal dipertahankan. */
export function uraiJumlah(value) {
    return parseFloat(String(value == null ? '' : value).replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
}

/** Nominal uang: hanya digit, "15.000" -> 15000. */
export function uraiUang(value) {
    return parseFloat(String(value == null ? '' : value).replace(/\D+/g, '')) || 0;
}

export function digitUang(value) {
    return String(value == null ? '' : value).replace(/\D+/g, '');
}

export function formatUang(value) {
    const digit = digitUang(value);
    return digit ? Number(digit).toLocaleString('id-ID') : '';
}

/** Alias untuk pemanggil yang memakai nama formatUangJs. */
export const formatUangJs = formatUang;
