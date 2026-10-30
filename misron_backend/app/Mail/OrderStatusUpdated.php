<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;
use App\Models\User;

class OrderStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $order;
    public $isAdmin;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param Order $order
     * @param bool $isAdmin
     * @return void
     */
    public function __construct(User $user, Order $order, $isAdmin = false)
    {
        $this->user = $user;
        $this->order = $order;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Subject of the email
        $subject = $this->isAdmin 
            ? 'Admin Notification: Order Status Updated' 
            : 'Your Order Status Has Been Updated';

        return $this->view('emails.order_status_updated')
                    ->subject($subject)
                    ->with([
                        'user' => $this->user,
                        'order' => $this->order,
                        'isAdmin' => $this->isAdmin,
                    ]);
    }
}
