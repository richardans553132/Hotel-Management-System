# Hotel Booking System - Josil

A responsive, secure, and modern Hotel Booking System developed for the ICT243 Web Programming assignment. Built using PHP, MySQL (PDO), HTML5, CSS3, JavaScript, and Bootstrap 5.3.

---

## Folder Structure

```
c:/xampp/htdocs/hotel/
│
├── config/
│   └── db.php                  # Database connection settings (PDO)
│
├── database/
│   └── schema.sql              # SQL Schema export
│
├── assets/
│   ├── css/
│   │   └── style.css           # Premium blue/white custom styling & typography
│   └── js/
│       └── main.js             # General app JS (e.g. reservation price calculator)
│
├── includes/
│   ├── auth.php                # Authentication helper (session checks)
│   ├── header.php              # Header template (Bootstrap 5, Icons, custom CSS)
│   ├── navbar.php              # Main navigation header
│   └── footer.php              # Footer template & JS scripts
│
├── pages/
│   ├── rooms.php               # Rooms CRUD (Bootstrap tables + modals)
│   ├── guests.php              # Guests CRUD (Bootstrap tables + modals)
│   ├── reservations.php        # Reservations CRUD & automatic price calculator
│   └── payments.php            # Payments CRUD & booking links
│
├── index.php                   # Landing/Redirect logic
├── login.php                   # Secure Admin login interface
├── logout.php                  # Session termination
├── setup.php                   # Database initializer and admin account seeder
└── README.md                   # Setup guide and instructions
```

---

## Step-by-Step Setup Instructions

Follow these instructions to run the application locally on Windows using XAMPP:

### Step 1: Place files in XAMPP Directory
Make sure the project files are located inside your XAMPP's `htdocs` directory at:
`C:\xampp\htdocs\hotel`

### Step 2: Start Apache and MySQL
1. Launch the **XAMPP Control Panel**.
2. Click the **Start** button next to **Apache**.
3. Click the **Start** button next to **MySQL**.
4. Confirm both services show a green status background.

### Step 3: Run the Automatic Setup Wizard
1. Open your web browser (Chrome, Edge, or Firefox).
2. Navigate to: `http://localhost/hotel/setup.php`
3. Click the blue **Start Installation** button.
4. The script will automatically:
   - Connect to your MySQL server.
   - Create the `hotel_booking_system` database.
   - Establish all tables (`users`, `rooms`, `guests`, `reservations`, `payments`).
   - Create constraints and relationships.
   - Seed a default administrator login (`admin` / `admin123`).
   - Seed sample rooms and guests to demonstrate features immediately.

---

## Testing Each Feature (Step-by-Step)

Once installation completes, click **Proceed to Login** or go to `http://localhost/hotel/login.php`.

### 1. Test Admin Login & Authentication
1. Enter username: `admin` and password: `admin123`.
2. Click **Sign In**.
3. You will be redirected to the **Dashboard**.
4. Test Security: Try copying the URL `http://localhost/hotel/dashboard.php`, logging out, closing your browser, opening it, pasting the link. You should be redirected back to the login page immediately.

### 2. Test Dashboard View
- Review the total cards: total rooms, registered guests, reservations count, and total revenue collected.
- View the **Recent Reservations** table.
- Verify the responsive design by resizing your browser window or using Chrome DevTools (Ctrl+Shift+I -> toggle device toolbar).

### 3. Test Rooms CRUD
1. Go to **Rooms** in the navbar.
2. **CREATE**: Click the **Add New Room** button at the top right. Enter a room number (e.g. `104`), select a room type (e.g., `Deluxe Suite`), price per night (e.g. `150.00`), and status `Available`. Click **Save Room**. Check if it appears in the list.
3. **READ & SEARCH**: Type `104` in the search bar and click **Filter**. Only your new room should appear. Click **Reset** to clear the search.
4. **UPDATE**: Click **Edit** next to Room 104. Change the status to `Maintenance` or change the price. Click **Update Room**. The badge and price list will update instantly.
5. **DELETE**: Click **Delete** next to Room 104. Confirm the warning dialog. The room is removed from the database.

### 4. Test Guests CRUD
1. Go to **Guests** in the navbar.
2. **CREATE**: Click **Register New Guest**. Fill out the details (First Name: `John`, Last Name: `Doe`, Email: `john.doe@example.com`, Phone: `+123456`, Address: `123 Main St`). Click **Register Guest**.
3. **READ & SEARCH**: Search for `John` in the search bar to filter the guest record.
4. **UPDATE**: Click **Edit** next to John Doe. Modify their email address or phone number and click **Update Details**.
5. **DELETE**: Click **Delete** next to a guest. Confirm the prompt. The guest details are removed.

### 5. Test Reservations CRUD (Double Booking Prevention & Cost Calculator)
1. Go to **Reservations** in the navbar.
2. **CREATE (Cost Calculator)**: Click **New Booking**.
   - Select a Guest (e.g., `Alice Smith`).
   - Select a Room (e.g. `Room 101` at `GH₵80/night`).
   - Pick a **Check-in Date** (e.g., tomorrow's date) and a **Check-out Date** (e.g. 2 days after check-in).
   - Watch the **Total Price** input and the nights badge update automatically in real-time (should display `2 nights` and `GH₵160.00`).
   - Set status to `Confirmed` and click **Create Booking**.
3. **Double Booking Prevention**: Try booking **Room 101** again with overlapping check-in/out dates. The system should throw a red banner alert stating: *"Double Booking Alert: The selected room is already booked/occupied during these dates."*
4. **UPDATE**: Click **Edit** on a reservation, change dates or status, and verify that the price and room statuses change.
5. **DELETE**: Click **Delete** to remove a booking.

### 6. Test Payments CRUD (Remaining Balance Integration)
1. Go to **Reservations** and click the green **Pay** button next to your reservation.
2. This redirects you to the **Payments** page and auto-opens the **Record New Payment** modal with that reservation pre-selected.
3. The remaining balance badge will show how much is owed (e.g., `Remaining Balance: GH₵160.00`). The input field will automatically pre-populate with `GH₵160.00`.
4. Select `Credit Card`, click **Record Transaction**.
5. The payment will show in the transaction list.
6. Open the payment modal again, select the same reservation. The balance badge should show `Remaining Balance: GH₵0.00` because the payment has been completed.
7. Go back to the **Dashboard** and notice the **Revenue Collected** card has increased by the payment amount!

---

## Automatic Hubtel SMS Booking Confirmations

When a receptionist creates a new reservation from the **Reservations** dashboard, the system automatically triggers an SMS confirmation via the **Hubtel SMS API** to the guest's mobile phone.

### Configuration (.env)
Add your Hubtel credentials to `.env`:
```env
HUBTEL_CLIENT_ID=your_client_id_here
HUBTEL_CLIENT_SECRET=your_client_secret_here
HUBTEL_SENDER_ID=JosselHotel
```

### Features Included:
- **Automatic Phone Number Formatting**: Converts Ghanaian mobile numbers (e.g., `0241234567`, `233...`) automatically into standard international format (`+233...`).
- **Phone Validation**: Ensures numbers are valid before attempting API requests.
- **Fail-Safe Booking**: If SMS delivery fails (e.g. network timeout or missing credentials), the booking remains safely saved in MySQL, and a notification banner indicates the status.
- **Duplicate SMS Prevention**: Uses button disabling, session submission tokens, and database deduplication to prevent duplicate requests when clicking "Create Booking" multiple times.
- **Audit Logging**: All SMS delivery attempts, responses, and statuses are logged into the `sms_logs` table.
