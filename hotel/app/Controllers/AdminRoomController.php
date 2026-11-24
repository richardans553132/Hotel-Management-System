<?php
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\InputValidator;
use App\Helpers\ImageHelper;
use App\Models\Room;
use App\Models\Hotel;
use App\Helpers\RateLimiter;

/**
 * AdminRoomController – CRUD for rooms (admin side)
 */
class AdminRoomController
{
    /** List all rooms */
    public function list()
    {
        $roomModel = new Room();
        $rooms = $roomModel->all();
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/admin/rooms/list.php';
    }

    /** Show form to create a new room */
    public function createForm()
    {
        // Need list of hotels for dropdown
        $hotels = (new Hotel())->all();
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/admin/rooms/create.php';
    }

    /** Handle POST create */
    public function handleCreate()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        // Basic validation
        $hotel_id       = intval($_POST['hotel_id'] ?? 0);
        $room_number    = InputValidator::text($_POST['room_number'] ?? '', 20);
        $type           = InputValidator::text($_POST['type'] ?? '', 50);
        $price          = floatval($_POST['price'] ?? 0);
        $capacity       = intval($_POST['capacity'] ?? 2);
        $description    = InputValidator::text($_POST['description'] ?? '', 2000);
        $amenities      = InputValidator::text($_POST['amenities'] ?? '', 1000);
        $image          = null;

        // Optional image upload (single primary image)
        if (!empty($_FILES['image']['name'])) {
            $saved = ImageHelper::store($_FILES['image'], __DIR__ . '/../../public/uploads/room_images');
            if ($saved) {
                $image = $saved;
            }
        }

        $room = new Room();
        $roomId = $room->create([
            'hotel_id'      => $hotel_id,
            'room_number'   => $room_number,
            'type'          => $type,
            'price_per_night'=> $price,
            'capacity'      => $capacity,
            'description'   => $description,
            'amenities'     => $amenities,
            'image'         => $image ?? 'default_room.jpg',
            'status'        => 'available'
        ]);

        header('Location: ?page=admin_rooms');
        exit;
    }

    /** Show edit form */
    public function editForm()
    {
        $id = intval($_GET['id'] ?? 0);
        $room = (new Room())->find($id);
        if (!$room) {
            die('Room not found');
        }
        $hotels = (new Hotel())->all();
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/admin/rooms/edit.php';
    }

    /** Handle POST edit */
    public function handleEdit()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        $id = intval($_POST['id'] ?? 0);
        $room = new Room();
        $existing = $room->find($id);
        if (!$existing) {
            die('Room not found');
        }
        $data = [];
        foreach (['hotel_id','room_number','type','price_per_night','capacity','description','amenities','status'] as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                // cast numeric fields appropriately
                if (in_array($field, ['hotel_id','capacity'])) {
                    $data[$field] = intval($_POST[$field]);
                } elseif ($field === 'price_per_night') {
                    $data[$field] = floatval($_POST[$field]);
                } else {
                    $data[$field] = $_POST[$field];
                }
            }
        }
        // Optional new image
        if (!empty($_FILES['image']['name'])) {
            $saved = ImageHelper::store($_FILES['image'], __DIR__ . '/../../public/uploads/room_images');
            if ($saved) {
                $data['image'] = $saved;
            }
        }
        $room->update($id, $data);
        header('Location: ?page=admin_rooms');
        exit;
    }

    /** Delete a room */
    public function delete()
    {
        if (!Security::validateCsrfToken($_GET['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        $id = intval($_GET['id'] ?? 0);
        (new Room())->delete($id);
        header('Location: ?page=admin_rooms');
        exit;
    }
}
?>
