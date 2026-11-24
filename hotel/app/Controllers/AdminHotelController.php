<?php
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\InputValidator;
use App\Helpers\ImageHelper;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomImage;
use App\Helpers\RateLimiter;

/**
 * AdminHotelController – CRUD for hotels (admin side)
 */
class AdminHotelController
{
    /** List all hotels */
    public function list()
    {
        $hotelModel = new Hotel();
        $hotels = $hotelModel->all();
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/admin/hotels/list.php';
    }

    /** Show form to create a new hotel */
    public function createForm()
    {
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/admin/hotels/create.php';
    }

    /** Handle POST create */
    public function handleCreate()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        // Basic validation (real‑world would be more thorough)
        $name        = InputValidator::text($_POST['name'] ?? '', 150);
        $slug        = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $description = InputValidator::text($_POST['description'] ?? '', 2000);
        $address     = InputValidator::text($_POST['address'] ?? '', 255);
        $city        = InputValidator::text($_POST['city'] ?? '', 100);
        $country     = InputValidator::text($_POST['country'] ?? '', 100);
        $phone       = InputValidator::text($_POST['phone'] ?? '', 30);
        $email       = InputValidator::email($_POST['email'] ?? '');
        $starRating  = floatval($_POST['star_rating'] ?? 0);
        $coverImage  = null;

        // Handle optional cover image upload
        if (!empty($_FILES['cover_image']['name'])) {
            $saved = ImageHelper::store($_FILES['cover_image'], __DIR__ . '/../../public/uploads/hotel_images');
            if ($saved) {
                $coverImage = $saved;
            }
        }

        $hotel = new Hotel();
        $hotelId = $hotel->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'phone' => $phone,
            'email' => $email,
            'star_rating' => $starRating,
            'cover_image' => $coverImage ?? 'default_hotel.jpg'
        ]);

        header('Location: ?page=admin_hotels');
        exit;
    }

    /** Show edit form */
    public function editForm()
    {
        $id = intval($_GET['id'] ?? 0);
        $hotel = (new Hotel())->find($id);
        if (!$hotel) {
            die('Hotel not found');
        }
        $csrf = Security::generateCsrfToken();
        require __DIR__ . '/../Views/admin/hotels/edit.php';
    }

    /** Handle POST edit */
    public function handleEdit()
    {
        if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        $id = intval($_POST['id'] ?? 0);
        $hotel = new Hotel();
        $existing = $hotel->find($id);
        if (!$existing) {
            die('Hotel not found');
        }
        // Validate same fields as create (allow empty to keep old values)
        $data = [];
        foreach (['name','description','address','city','country','phone','email','star_rating'] as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $data[$field] = $_POST[$field];
            }
        }
        // Optional new cover image
        if (!empty($_FILES['cover_image']['name'])) {
            $saved = ImageHelper::store($_FILES['cover_image'], __DIR__ . '/../../public/uploads/hotel_images');
            if ($saved) {
                $data['cover_image'] = $saved;
            }
        }
        $hotel->update($id, $data);
        header('Location: ?page=admin_hotels');
        exit;
    }

    /** Delete hotel */
    public function delete()
    {
        if (!Security::validateCsrfToken($_GET['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
        $id = intval($_GET['id'] ?? 0);
        (new Hotel())->delete($id);
        header('Location: ?page=admin_hotels');
        exit;
    }
}
?>
