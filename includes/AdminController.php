<?php

declare(strict_types=1);

namespace Dizzy\Reservations;

defined('ABSPATH') || exit;

final class AdminController
{
    private const STATUSES=['pending','confirmed','waitlisted','cancelled'];
    public function __construct(private ReservationRepository $repository,private ReservationService $service,private TicketService $tickets) {}
    public function register(): void { add_action('admin_menu',[$this,'menu']);add_action('admin_post_dizzy_reservation_status',[$this,'status']);add_action('admin_post_dizzy_reservation_checkin',[$this,'checkin']);add_action('admin_post_dizzy_reservation_undo_checkin',[$this,'undo']); }
    public function menu(): void { add_submenu_page('edit.php?post_type=dizzy_event',__('Reservations','dizzy-reservations-manager'),__('Reservations','dizzy-reservations-manager'),'manage_options','dizzy-reservations',[$this,'render']); }

    public function render(): void
    {
        if(!current_user_can('manage_options')) return;echo '<div class="wrap"><h1>'.esc_html__('Reservations','dizzy-reservations-manager').'</h1><table class="widefat striped"><thead><tr><th>Name</th><th>Event</th><th>Guests</th><th>Status</th><th>Ticket / Check-in</th></tr></thead><tbody>';
        foreach($this->repository->all() as $row){$id=(int)$row['id'];echo '<tr><td>'.esc_html((string)$row['name']).'<br>'.esc_html((string)$row['email']).'</td><td>'.esc_html(get_the_title((int)$row['event_id'])).'</td><td>'.esc_html((string)$row['guests']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="dizzy_reservation_status"><input type="hidden" name="reservation_id" value="'.$id.'">';wp_nonce_field('dizzy_reservation_'.$id);echo '<select name="status">';foreach(self::STATUSES as $status)echo '<option value="'.esc_attr($status).'" '.selected($row['status'],$status,false).'>'.esc_html(ucfirst($status)).'</option>';echo '</select> <button class="button">Save</button></form></td><td>';
            if($row['status']==='confirmed'){$url=$this->tickets->url($row);echo '<a class="button" href="'.esc_url($url).'">Ticket</a> <img src="'.esc_url($this->tickets->qrUrl($url)).'" width="70" height="70" alt="QR"> <form style="display:inline" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.(!empty($row['checked_in_at'])?'dizzy_reservation_undo_checkin':'dizzy_reservation_checkin').'"><input type="hidden" name="reservation_id" value="'.$id.'">';wp_nonce_field('dizzy_checkin_'.$id);echo '<button class="button">'.esc_html(!empty($row['checked_in_at'])?'Undo':'Check in').'</button></form>';}echo '</td></tr>';}
        echo '</tbody></table></div>';
    }

    private function authorizedId(): int { $id=absint($_POST['reservation_id']??0);if(!current_user_can('manage_options'))wp_die('Unauthorized');return $id; }
    private function redirect(): never { wp_safe_redirect(admin_url('edit.php?post_type=dizzy_event&page=dizzy-reservations'));exit; }
    public function status(): void { $id=$this->authorizedId();check_admin_referer('dizzy_reservation_'.$id);$status=sanitize_key((string)($_POST['status']??''));if(in_array($status,self::STATUSES,true))$this->service->changeStatus($id,$status);$this->redirect(); }
    public function checkin(): void { $id=$this->authorizedId();check_admin_referer('dizzy_checkin_'.$id);$this->repository->checkIn($id,get_current_user_id());$this->redirect(); }
    public function undo(): void { $id=$this->authorizedId();check_admin_referer('dizzy_checkin_'.$id);$this->repository->undoCheckIn($id);$this->redirect(); }
}
