<?php

declare(strict_types=1);

namespace App\Http\Requests\Achats;

use App\Domain\Achats\Models\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierInvoice::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:50'],
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'reception_id' => ['nullable', 'integer', 'exists:receptions,id'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.required' => 'Indiquez le numéro de la facture du fournisseur.',
            'purchase_order_id.required' => 'Choisissez le bon de commande facturé.',
            'invoice_date.before_or_equal' => 'La date de la facture ne peut pas être dans le futur.',
            'due_date.after_or_equal' => 'L\'échéance ne peut pas précéder la date de la facture.',
            'amount.integer' => 'Le montant est un entier en FCFA, sans décimales.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
        ];
    }

    /**
     * @return array{number: string, purchase_order_id: int, reception_id: int|null, invoice_date: string, due_date: string, amount: int}
     */
    public function invoiceData(): array
    {
        return [
            'number' => trim($this->string('number')->toString()),
            'purchase_order_id' => $this->integer('purchase_order_id'),
            'reception_id' => $this->filled('reception_id') ? $this->integer('reception_id') : null,
            'invoice_date' => $this->string('invoice_date')->toString(),
            'due_date' => $this->string('due_date')->toString(),
            'amount' => $this->integer('amount'),
        ];
    }
}
