<?php

namespace App\Enums;

enum NotificationType: string
{
    // Buyer-facing
    case OrderConfirmed = 'order_confirmed';
    case OrderReady = 'order_ready';
    case OrderCompleted = 'order_completed';
    case OrderCancelled = 'order_cancelled';
    case OrderMessage = 'order_message';

    // Farmer-seller facing
    case OrderPlaced = 'order_placed';
    case OrderAwaitingConfirmation = 'order_awaiting_confirmation';
    case ListingLowStock = 'listing_low_stock';
    case ListingTakenDown = 'listing_taken_down';
    case ListingRestored = 'listing_restored';
    case AccountApproved = 'account_approved';
    case FloorPriceRaised = 'floor_price_raised';
    case TawadCeilingLowered = 'tawad_ceiling_lowered';
    case FarmAnnouncement = 'farm_announcement';
    case FaqEntryModerated = 'faq_entry_moderated';
    case AccountDeletionRequested = 'account_deletion_requested';
    case AccountDeletionRejected = 'account_deletion_rejected';
    case ReportSubmitted = 'report_submitted';
    case ReportResolved = 'report_resolved';
    case ReportDismissed = 'report_dismissed';
    case ReservationMade = 'reservation_made';
    case ReservationConverted = 'reservation_converted';
    case ReservationCancelled = 'reservation_cancelled';
    case HarvestReminder = 'harvest_reminder';
    case ExpiredStockLeft = 'expired_stock_left';
    case PaymentProofSubmitted = 'payment_proof_submitted';
    case PaymentConfirmed = 'payment_confirmed';
    case PaymentRejected = 'payment_rejected';
    case PaymentDueSoon = 'payment_due_soon';
    case PaymentExpired = 'payment_expired';
    case PaymentCheckReminder = 'payment_check_reminder';
    case RefundDue = 'refund_due';
    case RefundCompleted = 'refund_completed';

    public function label(): string
    {
        return match ($this) {
            self::OrderConfirmed => 'Order confirmed',
            self::OrderReady => 'Order ready',
            self::OrderCompleted => 'Order completed',
            self::OrderCancelled => 'Order cancelled',
            self::OrderMessage => 'New order message',
            self::OrderPlaced => 'New order placed',
            self::OrderAwaitingConfirmation => 'Order awaiting confirmation',
            self::ListingLowStock => 'Listing low on stock',
            self::ListingTakenDown => 'Listing taken down',
            self::ListingRestored => 'Listing restored',
            self::AccountApproved => 'Account approved',
            self::FloorPriceRaised => 'Floor price raised above your listing',
            self::TawadCeilingLowered => 'Tawad limit lowered below your discount',
            self::FarmAnnouncement => 'Farm announcement',
            self::FaqEntryModerated => 'FAQ answer moderated',
            self::AccountDeletionRequested => 'Account deletion requested',
            self::AccountDeletionRejected => 'Account deletion request rejected',
            self::ReportSubmitted => 'New report submitted',
            self::ReportResolved => 'Report resolved',
            self::ReportDismissed => 'Report dismissed',
            self::ReservationMade => 'Reservation made',
            self::ReservationConverted => 'Reservation converted',
            self::ReservationCancelled => 'Reservation cancelled',
            self::HarvestReminder => 'Record the actual harvest',
            self::ExpiredStockLeft => 'Stock left after the listing ended',
            self::PaymentProofSubmitted => 'Payment proof submitted',
            self::PaymentConfirmed => 'Payment confirmed',
            self::PaymentRejected => 'Payment rejected',
            self::PaymentDueSoon => 'Payment due soon',
            self::PaymentExpired => 'Payment time expired',
            self::PaymentCheckReminder => 'Payment waiting for your check',
            self::RefundDue => 'Refund due',
            self::RefundCompleted => 'Refund completed',
        };
    }

    public static function forOrderStatus(OrderStatus $status): ?self
    {
        return match ($status) {
            OrderStatus::Placed => self::OrderPlaced,
            OrderStatus::Confirmed => self::OrderConfirmed,
            OrderStatus::Ready => self::OrderReady,
            OrderStatus::Completed => self::OrderCompleted,
            OrderStatus::Cancelled => self::OrderCancelled,
        };
    }
}
