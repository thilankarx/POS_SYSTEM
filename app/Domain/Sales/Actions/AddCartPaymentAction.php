<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Loyalty\Exceptions\LoyaltyException;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Support\Money\Money;

final class AddCartPaymentAction
{
    public function execute(Cart $cart, PaymentMethod $method, string $amount, ?string $tendered = null, ?string $reference = null): CartPayment
    {
        if ($cart->status !== Cart::STATUS_ACTIVE) {
            throw CheckoutException::cartNotActive($cart->status);
        }

        if (! $method->is_active) {
            throw CheckoutException::paymentMethodInactive();
        }

        // A manually-run card payment always needs the terminal's
        // approval/slip number recorded against it for later
        // reconciliation with the card machine's batch, on top of any
        // method explicitly flagged requires_reference (check, giftcard,
        // ...). Integrated card providers (stripe_terminal, ...) capture
        // their own provider reference, so this only applies to manual.
        $manualCard = $method->kind === 'card' && $method->provider === 'manual';

        if (($method->requires_reference || $manualCard) && blank($reference)) {
            throw CheckoutException::referenceRequired();
        }

        // Optimistic checks only, for immediate cashier feedback -- the
        // authoritative, race-safe debit happens under a row lock inside
        // CompleteSaleAction, exactly like coupon/promotion validation.
        if ($method->code === 'giftcard') {
            $this->assertGiftcardUsable($reference, $amount);
        }

        if ($method->code === 'points') {
            $this->assertPointsAvailable($cart, $amount);
        }

        return CartPayment::create([
            'cart_id' => $cart->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'tendered' => $tendered,
            'reference' => $reference,
        ]);
    }

    private function assertGiftcardUsable(?string $reference, string $amount): void
    {
        $giftcard = Giftcard::where('number', $reference)->first();

        if ($giftcard === null) {
            throw GiftcardException::notFound();
        }

        if (! $giftcard->is_active) {
            throw GiftcardException::inactive();
        }

        if ($giftcard->expires_at !== null && $giftcard->expires_at->isPast()) {
            throw GiftcardException::expired();
        }

        if ($giftcard->balance->isLessThan(Money::of($amount))) {
            throw GiftcardException::insufficientBalance();
        }
    }

    private function assertPointsAvailable(Cart $cart, string $amount): void
    {
        if ($cart->customer_id === null) {
            throw LoyaltyException::noLoyaltyPackage();
        }

        $customer = $cart->customer ?? $cart->customer()->first();
        $package = $customer?->loyaltyPackage;

        if ($package === null || bccomp((string) $package->currency_value_per_point, '0', 4) <= 0) {
            throw LoyaltyException::noLoyaltyPackage();
        }

        $pointsNeeded = bcdiv($amount, (string) $package->currency_value_per_point, 3);

        if (bccomp((string) $customer->points_balance, $pointsNeeded, 3) < 0) {
            throw LoyaltyException::insufficientPoints();
        }
    }
}
