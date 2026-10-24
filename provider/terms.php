
<?php
session_start();

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'provider'
) {
    header('Location: ../views/login.php');
    exit;
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms and Conditions | I'll BeCare</title>
    <link rel="stylesheet" href="../Assets/provider_dashboard.css?v=terms-format-20261024">
</head>
<body>
<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="brand">I'll BeCare</div>
        <p class="sidebar-label">PROVIDER MENU</p>

        <a class="nav-link" href="provider_dashboard.php">Dashboard</a>
        <a class="nav-link" href="provider_page.php?page=bookings">My Bookings</a>
        <a class="nav-link" href="provider_page.php?page=services">My Services</a>
        <a class="nav-link" href="provider_page.php?page=reviews">Client Reviews</a>
        <a class="nav-link" href="provider_page.php?page=notifications">Notifications</a>
        <a class="nav-link" href="provider_page.php?page=profile">My Profile</a>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <p class="eyebrow">PROVIDER PORTAL</p>
                <h1>Terms and Conditions</h1>
                <p class="muted">I'll BeCare Provider Platform</p>
            </div>
        </header>

        <section class="content-panel terms-document">
            <h2>I'll BeCare Terms and Conditions</h2>
            <p><strong>Last updated: October 24, 2026</strong></p>

            <h3>1. About I'll BeCare</h3>
            <p>I'll BeCare is an academic prototype developed by BSIT III students of Siquijor State College for a school requirement. It is a web platform that helps households in Siquijor Island (Clients) find and book approved care and household support Providers for short-term, on-demand services. It is not a commercial service, it does not collect fees, and it is not a recruitment or employment agency.</p>

            <h3>2. Who may use the system</h3>
            <p class="terms-clause"><strong>2.1</strong> You must be at least 18 years old to register as a Client or a Provider.</p>
            <p class="terms-clause"><strong>2.2</strong> You must give true, complete, and current information, and keep your password private.</p>
            <p class="terms-clause"><strong>2.3</strong> The Administrator may confirm, block, or suspend any account that breaks these terms.</p>

            <h3>3. Nature of the services</h3>
            <p class="terms-clause"><strong>3.1</strong> Services are short-term and on-demand (hourly, half-day, full-day, custom, or same-day). The system does not support long-term or live-in arrangements.</p>
            <p class="terms-clause"><strong>3.2</strong> Providers are not employees of I'll BeCare or of the group. A booking is a one-time arrangement between a Client and a Provider and does not create a continuing employment relationship.</p>
            <p class="terms-clause"><strong>3.3</strong> A booking may not exceed 8 hours of service per day, and a meal break must be allowed for longer bookings. Arrangements must not go below the minimum labor standards under the Labor Code and other labor laws.</p>

            <h3>4. Provider requirements</h3>
            <p class="terms-clause"><strong>4.1</strong> Providers must submit a valid government ID and a Barangay Clearance for review. An NBI Clearance is recommended.</p>
            <p class="terms-clause"><strong>4.2</strong> Only Providers approved by the Administrator are shown to Clients. The Administrator may reject or suspend a Provider whose documents are unclear, invalid, or false.</p>
            <p class="terms-clause"><strong>4.3</strong> Providers set their own hourly rates and may change them. The rate shown at booking is the rate that applies to that booking.</p>
            <p class="terms-clause"><strong>4.4</strong> Providers may accept or decline any booking request.</p>

            <h3>5. Bookings, cost, and payment</h3>
            <p class="terms-clause"><strong>5.1</strong> A Client sends a booking request. The booking is confirmed only when the Provider accepts it.</p>
            <p class="terms-clause"><strong>5.2</strong> The system shows a cost summary (the Provider's hourly rate multiplied by the booked hours) before the request is sent.</p>
            <p class="terms-clause"><strong>5.3</strong> Payment is made face-to-face between the Client and the Provider after the service. The system does not process payments and does not charge any fee, service charge, or commission.</p>
            <p class="terms-clause"><strong>5.4</strong> Clients and Providers may cancel a booking through the system. Please cancel as early as possible so the other party can make other plans.</p>

            <h3>6. Safety and conduct</h3>
            <p class="terms-clause"><strong>6.1</strong> Clients must give accurate care details, including emergency contact information, so the Provider can respond properly.</p>
            <p class="terms-clause"><strong>6.2</strong> Users must treat each other with respect and must not harass, abuse, or put anyone at risk.</p>
            <p class="terms-clause"><strong>6.3</strong> The system does not independently investigate or authenticate documents beyond the Administrator's review. Clients should still use their own judgment.</p>
            <p class="terms-clause"><strong>6.4</strong> Ratings and reviews must be honest. The Administrator may remove reviews that break these terms.</p>

            <h3>7. Complaints</h3>
            <p>Clients and Providers may file a complaint or safety concern through the system. The Administrator will review and resolve it within the platform. The system does not mediate or enforce legal action, and serious matters should be reported to the proper authorities.</p>

            <h3>8. Privacy</h3>
            <p>Your personal data, including IDs, clearances, and emergency contact details, is collected only for verification and booking and handled under the Data Privacy Act of 2012 (RA 10173), as explained in the Privacy Notice. Submitted documents are seen only by authorized Administrators.</p>

            <h3>9. Prototype limits</h3>
            <p class="terms-clause"><strong>9.1</strong> The system is for academic use. Use test data only and do not rely on it for emergencies.</p>
            <p class="terms-clause"><strong>9.2</strong> The group makes no promise that the system will always be available or error-free.</p>
            <p class="terms-clause"><strong>9.3</strong> Any commercial use would first require the appropriate DOLE license and updated terms.</p>

            <h3>10. Changes</h3>
            <p>The group may update these terms. Continued use means you accept the updated terms. By ticking the box at registration, you confirm that you have read and agree to these Terms and Conditions and the Privacy Notice.</p>

            <a href="provider_page.php?page=profile" class="button">
                Back to My Profile
            </a>
        </section>
    </main>
</div>
</body>
</html>
