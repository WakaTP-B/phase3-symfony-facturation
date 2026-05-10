import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['items', 'productName', 'productSelect', 'productPrice', 'quantity', 'total']
    static values = { index: Number }

    connect() {
        const existing = JSON.parse(this.itemsTarget.dataset.existingLines || '[]')
        existing.forEach(line => {
            this.restoreLine(line.name, parseFloat(line.price), parseInt(line.quantity))
        })
        this.updateTotal()
    }

    validateForm(event) {
        const client = document.querySelector('#invoice_client')
        const errorSpan = document.querySelector('#invoice_client + span') || client.nextElementSibling

        if (!client || !client.value) {
            event.preventDefault()
            client.classList.add('border-red-500')
            if (errorSpan) {
                errorSpan.textContent = 'Veuillez choisir un client'
            }
            client.focus()
            return
        }

        client.classList.remove('border-red-500')
        if (errorSpan) errorSpan.textContent = ''
    }

    selectProduct(event) {
        const select = event.target
        const selected = select.options[select.selectedIndex]

        if (selected.value === '__custom__') {
            this.productNameTarget.classList.remove('hidden')
            this.productNameTarget.focus()
            this.productNameTarget.value = ''
            this.productPriceTarget.value = ''
            this.productPriceTarget.removeAttribute('readonly')
            this.productPriceTarget.classList.remove('bg-gray-50', 'text-[#4A5565]', 'cursor-not-allowed', 'no-arrows')
            this.productPriceTarget.classList.add('bg-white', 'text-[#101828]')
        } else if (selected.value !== '') {
            this.productNameTarget.classList.add('hidden')
            this.productNameTarget.value = selected.value
            this.productPriceTarget.value = selected.dataset.price
            this.productPriceTarget.setAttribute('readonly', true)
            this.productPriceTarget.classList.add('bg-gray-50', 'text-[#4A5565]', 'cursor-not-allowed', 'no-arrows')
            this.productPriceTarget.classList.remove('bg-white', 'text-[#101828]')
        }
    }

    addLine(event) {
        event.preventDefault()
        const name = this.productNameTarget.value.trim()
        const price = parseFloat(this.productPriceTarget.value)
        const quantity = parseInt(this.quantityTarget.value)

        if (!name || isNaN(price) || isNaN(quantity) || quantity < 1) {
            alert('Veuillez remplir tous les champs correctement')
            return
        }

        const total = price * quantity
        const index = this.indexValue

        const row = document.createElement('div')
        row.classList.add('line-item', 'grid', 'grid-cols-[2fr_1fr_1fr_1fr_40px]', 'items-center', 'py-3', 'border-b', 'border-[#F3F4F6]', 'gap-4')
        row.innerHTML = `
            <div>
                <p class="text-sm font-medium text-[#101828]">${name}</p>
            </div>
            <span class="text-sm text-[#4A5565] text-center">${quantity}</span>
            <span class="text-sm text-[#4A5565] text-right">${price.toFixed(2)} €</span>
            <span class="text-sm font-medium text-[#101828] text-right">${total.toFixed(2)} €</span>
            <button type="button" data-action="invoice#removeLine" class="flex items-center justify-center text-[#E7000B] hover:text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            </button>
            <input type="hidden" name="invoice_lines[${index}][name]" value="${name}">
            <input type="hidden" name="invoice_lines[${index}][price]" value="${price}">
            <input type="hidden" name="invoice_lines[${index}][quantity]" value="${quantity}">
        `

        this.itemsTarget.appendChild(row)
        this.indexValue = index + 1

        this.productSelectTarget.selectedIndex = 0
        this.productNameTarget.value = ''
        this.productNameTarget.classList.add('hidden')
        this.productPriceTarget.value = ''
        this.quantityTarget.value = '1'

        this.updateTotal()
    }

    restoreLine(name, price, quantity) {
        const total = price * quantity
        const index = this.indexValue
        const row = document.createElement('div')
        row.classList.add('line-item', 'grid', 'grid-cols-[2fr_1fr_1fr_1fr_40px]', 'items-center', 'py-3', 'border-b', 'border-[#F3F4F6]', 'gap-4')
        row.innerHTML = `
        <div><p class="text-sm font-medium text-[#101828]">${name}</p></div>
        <span class="text-sm text-[#4A5565] text-center">${quantity}</span>
        <span class="text-sm text-[#4A5565] text-right">${price.toFixed(2)} €</span>
        <span class="text-sm font-medium text-[#101828] text-right">${total.toFixed(2)} €</span>
        <button type="button" data-action="invoice#removeLine" class="flex items-center justify-center text-[#E7000B] hover:text-red-700">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
        </button>
        <input type="hidden" name="invoice_lines[${index}][name]" value="${name}">
        <input type="hidden" name="invoice_lines[${index}][price]" value="${price}">
        <input type="hidden" name="invoice_lines[${index}][quantity]" value="${quantity}">
    `
        this.itemsTarget.appendChild(row)
        this.indexValue = index + 1
    }

    removeLine(event) {
        event.preventDefault()
        event.target.closest('.line-item').remove()
        this.updateTotal()
    }

    updateTotal() {
        let total = 0
        this.itemsTarget.querySelectorAll('.line-item').forEach(row => {
            const price = parseFloat(row.querySelector('input[name*="[price]"]')?.value || 0)
            const qty = parseInt(row.querySelector('input[name*="[quantity]"]')?.value || 0)
            total += price * qty
        })
        this.totalTarget.textContent = total.toFixed(2) + ' €'
    }
}