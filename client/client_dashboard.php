<?php require __DIR__ . '/client_logic.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Client Dashboard | I'll BeCare</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../Assets/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../Assets/client_style.css?v=client-terms-20261024">
</head>

<body>

    <!-- ICON SPRITE -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
        <symbol id="i-home" viewBox="0 0 24 24">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
            <polyline points="9 22 9 12 15 12 15 22" />
        </symbol>
        <symbol id="i-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
        </symbol>
        <symbol id="i-card" viewBox="0 0 24 24">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
            <line x1="1" y1="10" x2="23" y2="10" />
        </symbol>
        <symbol id="i-star" viewBox="0 0 24 24">
            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </symbol>
        <symbol id="i-flag" viewBox="0 0 24 24">
            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
            <line x1="4" y1="22" x2="4" y2="15" />
        </symbol>
        <symbol id="i-bell" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </symbol>
        <symbol id="i-user" viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </symbol>
        <symbol id="i-logout" viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
        </symbol>
    </svg>

    <div class="cd-shell">

        <!-- ============ SIDEBAR ============ -->
        <aside class="cd-sidebar">

            <a class="cd-brand" href="#overview">
                <img src="../Assets/Logo.png" alt="I'll BeCare">
            </a>

            <nav class="cd-nav" aria-label="Client menu">
                <a class="cd-nav-link" href="client_dashboard.php#overview"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-home" />
                    </svg><span>Overview</span></a>
                <a class="cd-nav-link" href="client_dashboard.php#find"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-search" />
                    </svg><span>Find Providers</span></a>
                <a class="cd-nav-link" href="client_dashboard.php#bookings"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-calendar" />
                    </svg><span>My Bookings</span></a>
                <a class="cd-nav-link" href="client_dashboard.php#cost"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-card" />
                    </svg><span>Cost Summary</span></a>
                <a class="cd-nav-link" href="client_dashboard.php#reviews"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-star" />
                    </svg><span>Reviews</span></a>
                <a class="cd-nav-link" href="client_dashboard.php#reports"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-flag" />
                    </svg><span>Reports</span></a>
                <a class="cd-nav-link" href="client_dashboard.php#notifications"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-bell" />
                    </svg><span>Notifications</span><?php if ($unread): ?><span class="cd-nav-badge" data-notif-count><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?></a>
                <a class="cd-nav-link" href="client_dashboard.php#profile"><svg class="cd-icon" aria-hidden="true">
                        <use href="#i-user" />
                    </svg><span>My Profile</span></a>
            </nav>

            <a class="cd-nav-link cd-logout" href="../views/logout.php">
                <svg class="cd-icon" aria-hidden="true">
                    <use href="#i-logout" />
                </svg>
                <span>Log out</span>
            </a>

        </aside>


        <!-- ============ CONTENT ============ -->
        <div class="cd-body">

            <header class="cd-topbar">
                <div class="cd-welcome">
                    <div class="cd-profile-avatar" aria-hidden="true">
                        <?php if (!empty($me['photo'])): ?>
                            <img src="../<?= e($me['photo']) ?>" alt="">
                        <?php else: ?>
                            <?= e(mb_strtoupper(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                    <h1 class="cd-title"><?= e(trim($me['first_name'] . ' ' . $me['last_name'])) ?></h1>
                </div>

                <a class="cd-bell <?= $unread ? 'has-new' : '' ?>" href="client_dashboard.php#notifications" aria-label="Notifications<?= $unread ? ' (' . $unread . ' new)' : '' ?>">
                    <svg class="cd-icon" aria-hidden="true">
                        <use href="#i-bell" />
                    </svg>
                    <?php if ($unread): ?>
                        <span class="cd-count" data-notif-count><?= $unread > 99 ? '99+' : $unread ?></span>
                    <?php endif; ?>
                </a>
            </header>

            <main class="cd-main container-fluid">

                <?php if ($flash): ?>
                    <div class="cd-alert <?= $flash['ok'] ? 'ok' : 'err' ?>" role="alert"><?= e($flash['msg']) ?></div>
                <?php endif; ?>

                <!-- ================= OVERVIEW ================= -->
                <section class="cd-view" id="overview">

                    <div class="cd-view-head">
                        <h2>Overview</h2>
                        <p>A quick look at your bookings and activity.</p>
                    </div>

                    <div class="cd-stats row g-3">
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-3">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-calendar" />
                                </svg></div>
                            <div><strong><?= $upcoming ?></strong><span>Upcoming bookings</span></div>
                        </div>
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-3">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-bell" />
                                </svg></div>
                            <div><strong><?= $counts['pending'] ?></strong><span>Pending requests</span></div>
                        </div>
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-3">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-star" />
                                </svg></div>
                            <div><strong><?= $counts['completed'] ?></strong><span>Completed services</span></div>
                        </div>
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-3">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-bell" />
                                </svg></div>
                            <div><strong><?= $unread ?></strong><span>Unread notifications</span></div>
                        </div>
                    </div>

                    <div class="cd-grid-2">

                        <div class="cd-card">
                            <h3 class="cd-card-title">Next booking</h3>
                            <?php if ($nextBooking): ?>
                                <div class="cd-cost">
                                    <div class="cd-cost-row"><span>Provider</span><span><?= e($nextBooking['pfirst'] . ' ' . $nextBooking['plast']) ?></span></div>
                                    <div class="cd-cost-row"><span>Service</span><span><?= e($nextBooking['category']) ?></span></div>
                                    <div class="cd-cost-row"><span>When</span><span><?= e(fmt_dt($nextBooking['start_datetime'])) ?></span></div>
                                    <div class="cd-cost-row"><span>Where</span><span><?= e($nextBooking['service_address']) ?></span></div>
                                    <div class="cd-cost-row cd-cost-total"><span>Total</span><span><?= money($nextBooking['total_cost']) ?></span></div>
                                </div>
                            <?php else: ?>
                                <div class="cd-empty">
                                    <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                        <use href="#i-calendar" />
                                    </svg>
                                    <strong>No upcoming booking</strong>
                                    <span>Your next confirmed booking will appear here.</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="cd-card">
                            <h3 class="cd-card-title">Quick actions</h3>
                            <div class="cd-quick">
                                <a class="cd-btn" href="client_dashboard.php#find">Find a Provider</a>
                                <a class="cd-btn cd-btn-outline" href="client_dashboard.php#bookings">My bookings</a>
                                <a class="cd-btn cd-btn-outline" href="client_dashboard.php#reports">Report an issue</a>
                            </div>
                        </div>

                    </div>

                    <div class="cd-card">
                        <h3 class="cd-card-title">Recent notifications</h3>
                        <?php if ($notes): ?>
                            <?php foreach (array_slice($notes, 0, 3) as $n): ?>
                                <div class="cd-note <?= $n['is_read'] ? '' : 'is-unread' ?>">
                                    <span class="cd-dot"></span>
                                    <div>
                                        <strong><?= e($n['title']) ?></strong>
                                        <p><?= e($n['message']) ?></p>
                                        <span class="cd-muted"><?= e(fmt_dt($n['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="cd-empty">
                                <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                    <use href="#i-bell" />
                                </svg>
                                <strong>You're all caught up</strong>
                                <span>New updates about your bookings will show here.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                </section>


                <!-- ================= FIND PROVIDERS ================= -->
                <section class="cd-view" id="find">

                    <div class="cd-view-head">
                        <h2>Find Providers</h2>
                        <p>Choose the care you need and see the verified Providers who offer it.</p>
                    </div>

                    <div class="cd-card">
                        <form class="cd-form" method="get" action="client_dashboard.php#find">
                            <div class="cd-row cd-three">
                                <div class="cd-field">
                                    <label for="category_id">Service category</label>
                                    <select id="category_id" name="category_id">
                                        <option value="">All services</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int)$cat['category_id'] ?>" <?= $catId === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                                <?= e($cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="cd-field">
                                    <label for="municipality">Municipality</label>
                                    <select id="municipality" name="municipality"
                                        data-selected="<?= e($municipality) ?>">
                                        <option value="">Any municipality</option>
                                        <?php foreach ($municipalities as $name): ?>
                                            <option value="<?= e($name) ?>" <?= $municipality === $name ? 'selected' : '' ?>>
                                                <?= e($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="cd-field">
                                    <label for="barangay">Barangay <span class="cd-muted">(optional)</span></label>
                                    <select id="barangay" name="barangay"
                                        data-selected="<?= e($barangay) ?>" <?= $municipality === '' ? 'disabled' : '' ?>>
                                        <option value="">Any barangay</option>
                                    </select>
                                </div>
                            </div>
                            <div class="cd-actions">
                                <button class="cd-btn" type="submit">Search Providers</button>
                                <a class="cd-btn cd-btn-outline" href="client_dashboard.php#find">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="cd-card">

                        <div class="cd-card-head">
                            <h3 class="cd-card-title">
                                Results<?= $catName !== '' ? ' · ' . e($catName) : '' ?><?= $municipality !== '' ? ' · ' . e($municipality) : '' ?><?= $barangay !== '' ? ' · ' . e($barangay) : '' ?>
                            </h3>
                            <span class="cd-muted"><?= count($providers) ?> Provider<?= count($providers) === 1 ? '' : 's' ?> found</span>
                        </div>

                        <?php if (!$providers): ?>
                            <div class="cd-empty">
                                <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                    <use href="#i-search" />
                                </svg>
                                <strong>No Providers available</strong>
                                <span>No approved Providers match the selected filters.</span>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($providers as $p):
                            $pid  = (int)$p['provider_id'];
                            $svcs = $servicesByProvider[$pid] ?? [];
                            $fullName = $p['first_name'] . ' ' . $p['last_name'];
                        ?>
                            <article class="cd-provider">
                                <div class="cd-avatar">
                                    <?php if (!empty($p['photo'])): ?>
                                        <img src="../<?= e($p['photo']) ?>" alt="">
                                    <?php else: ?>
                                        <?= e(mb_strtoupper(mb_substr($p['first_name'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="cd-provider-info">
                                    <h4><?= e($fullName) ?></h4>
                                    <p class="cd-muted"><?= e(implode(' · ', array_column($svcs, 'category'))) ?></p>
                                    <p class="cd-meta">
                                        <span class="cd-rating">
                                            <?= (int)$p['total_reviews'] > 0
                                                ? '★ ' . number_format((float)$p['avg_rating'], 1) . ' (' . (int)$p['total_reviews'] . ' review' . ((int)$p['total_reviews'] === 1 ? '' : 's') . ')'
                                                : 'No reviews yet' ?>
                                        </span>
                                        <span>
                                            <?= (int)$p['experience_years'] ?>
                                            year<?= (int)$p['experience_years'] === 1 ? '' : 's' ?> experience
                                        </span>
                                        <span><?= e($p['location']) ?></span>
                                    </p>
                                </div>
                                <div class="cd-provider-side">
                                    <?php foreach ($svcs as $s): ?>
                                        <div class="cd-svc">
                                            <span><?= e($s['category']) ?> · <strong><?= money($s['hourly_rate']) ?> / hour</strong></span>
                                            <a class="cd-btn cd-btn-sm" href="client_dashboard.php?service=<?= (int)$s['provider_service_id'] ?>#bookings">Book</a>
                                        </div>
                                    <?php endforeach; ?>
                                    <a class="cd-btn cd-btn-outline cd-btn-sm" href="client_dashboard.php?provider=<?= $pid ?>#provider">View profile</a>
                                </div>
                            </article>
                        <?php endforeach; ?>

                    </div>

                </section>


                <!-- ================= PROVIDER PROFILE ================= -->
                <section class="cd-view" id="provider">

                    <div class="cd-view-head">
                        <h2>Provider Profile</h2>
                        <p>Qualifications, services, rates and reviews.</p>
                    </div>

                    <?php if (!$viewProvider): ?>
                        <div class="cd-card">
                            <div class="cd-empty">
                                <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                    <use href="#i-user" />
                                </svg>
                                <strong>No Provider selected</strong>
                                <span>Open a Provider from <a href="client_dashboard.php#find">Find Providers</a>.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="cd-card">
                            <div class="cd-provider" style="border:0;padding:0">
                                <div class="cd-avatar">
                                    <?php if (!empty($viewProvider['photo'])): ?>
                                        <img src="../<?= e($viewProvider['photo']) ?>" alt="">
                                    <?php else: ?>
                                        <?= e(mb_strtoupper(mb_substr($viewProvider['first_name'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="cd-provider-info">
                                    <h4><?= e($viewProvider['first_name'] . ' ' . $viewProvider['last_name']) ?></h4>
                                    <p class="cd-meta">
                                        <span class="cd-rating">
                                            <?= (int)$viewProvider['total_reviews'] > 0 ? '★ ' . number_format((float)$viewProvider['avg_rating'], 1) . ' (' . (int)$viewProvider['total_reviews'] . ')' : 'No reviews yet' ?>
                                        </span>
                                        <span>
                                            <?= (int)$viewProvider['experience_years'] ?>
                                            <?= (int)$viewProvider['experience_years'] === 1 ? 'year' : 'years' ?>
                                            experience
                                        </span>
                                        <span><?= e($viewProvider['location']) ?></span>
                                    </p>
                                </div>
                            </div>

                            <div class="cd-cost">
                                <div class="cd-cost-row"><span>Skills</span><span><?= e($viewProvider['skills'] ?: 'Not provided') ?></span></div>
                                <div class="cd-cost-row"><span>Qualifications</span><span><?= e($viewProvider['qualifications'] ?: 'Not provided') ?></span></div>
                            </div>

                            <h3 class="cd-card-title">Services and rates</h3>
                            <?php foreach ($viewServices as $s): ?>
                                <div class="cd-svc">
                                    <span><?= e($s['category']) ?> · <strong><?= money($s['hourly_rate']) ?> / hour</strong></span>
                                    <a class="cd-btn cd-btn-sm" href="client_dashboard.php?service=<?= (int)$s['provider_service_id'] ?>#bookings">Book</a>
                                </div>
                            <?php endforeach; ?>

                            <h3 class="cd-card-title">Reviews</h3>
                            <?php if (!$viewReviews): ?>
                                <p class="cd-muted">No reviews yet.</p>
                            <?php endif; ?>
                            <?php foreach ($viewReviews as $rv): ?>
                                <div class="cd-note">
                                    <span class="cd-dot"></span>
                                    <div>
                                        <strong><?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?>
                                            · <?= e($rv['first_name'] . ' ' . mb_substr($rv['last_name'], 0, 1) . '.') ?></strong>
                                        <?php if (!empty($rv['comment'])): ?><p><?= e($rv['comment']) ?></p><?php endif; ?>
                                        <span class="cd-muted"><?= e(fmt_dt($rv['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </section>


                <!-- ================= MY BOOKINGS ================= -->
                <section class="cd-view" id="bookings">

                    <div class="cd-view-head">
                        <h2>My Bookings</h2>
                        <p>Track your requests, or send a new short-term booking.</p>
                    </div>

                    <div class="cd-card">

                        <div class="cd-chips" aria-label="Filter bookings by status">
                            <?php foreach ($statusKeys as $k): ?>
                                <a class="cd-chip <?= $status === $k ? 'is-active' : '' ?>" href="client_dashboard.php?status=<?= $k ?>#bookings">
                                    <?= e(ucfirst($k)) ?> <b><?= (int)$counts[$k] ?></b>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="cd-table-wrap table-responsive">
                            <table class="cd-table table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Provider</th>
                                        <th>Service</th>
                                        <th>Date &amp; time</th>
                                        <th>Duration</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$shownBookings): ?>
                                        <tr>
                                            <td class="cd-table-empty" colspan="7">No bookings yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($shownBookings as $b):
                                        $cancellable = in_array($b['status'], ['pending', 'confirmed'], true) && $b['start_datetime'] > $now;
                                    ?>
                                        <tr>
                                            <td><?= e($b['pfirst'] . ' ' . $b['plast']) ?></td>
                                            <td><?= e($b['category']) ?></td>
                                            <td><?= e(fmt_dt($b['start_datetime'])) ?> – <?= e(date('g:i A', strtotime($b['end_datetime']))) ?></td>
                                            <td><?= e(fmt_hours($b['duration_hours'])) ?> hours</td>
                                            <td><?= money($b['total_cost']) ?></td>
                                            <td><span class="cd-badge <?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                                            <td>
                                                <?php if ($cancellable): ?>
                                                    <form method="post" action="client_dashboard.php" data-confirm="Cancel this booking? The time slot will be released.">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="cancel_booking">
                                                        <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                                        <button class="cd-btn cd-btn-outline cd-btn-sm" type="submit">Cancel</button>
                                                    </form>
                                                <?php elseif ($b['status'] === 'cancelled'): ?>
                                                    <span class="cd-muted">Cancelled by <?= $b['cancelled_by'] === 'provider' ? 'Provider' : 'you' ?></span>
                                                <?php elseif ($b['status'] === 'completed'): ?>
                                                    <a class="cd-btn cd-btn-outline cd-btn-sm" href="client_dashboard.php#reviews">Review</a>
                                                <?php elseif ($b['status'] === 'declined'): ?>
                                                    <span class="cd-muted">Provider declined</span>
                                                <?php elseif ($b['status'] === 'expired'): ?>
                                                    <span class="cd-muted">No reply in 24 hours</span>
                                                <?php else: ?>
                                                    <?php
                                                    $endsAt = $b['end_datetime'];
                                                    if ($b['status'] === 'confirmed' && $now < $endsAt) {
                                                        echo '<span class="cd-muted">In progress</span>';
                                                    } elseif ($b['status'] === 'confirmed') {
                                                        echo '<span class="cd-muted">Waiting for Provider to mark complete</span>';
                                                    } elseif ($b['status'] === 'pending') {
                                                        echo '<span class="cd-muted">Start time passed</span>';
                                                    } else {
                                                        echo '<span class="cd-muted">-</span>';
                                                    }
                                                    ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="cd-hint">
                            A booking stays <strong>Pending</strong> until the Provider accepts.
                            If there is no reply within 24 hours it expires and the time is released.
                        </p>

                    </div>

                    <div class="cd-card">

                        <h3 class="cd-card-title">New booking request</h3>

                        <form class="cd-form" id="booking-form" method="post" action="client_dashboard.php"
                            data-rate="<?= $selSvc ? e($selSvc['hourly_rate']) : 0 ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create_booking">
                            <input type="hidden" name="provider_service_id" value="<?= $selSvc ? (int)$selSvc['provider_service_id'] : 0 ?>">

                            <div class="cd-field">
                                <label for="bk_provider">Selected Provider</label>
                                <input id="bk_provider" type="text" readonly
                                    value="<?= $selSvc ? e($selSvc['first_name'] . ' ' . $selSvc['last_name'] . ' — ' . $selSvc['category']) : '' ?>"
                                    placeholder="Choose a Provider from Find Providers">
                            </div>

                            <div class="cd-row">

                                <div class="cd-field">
                                    <label for="booking_type">Booking type</label>
                                    <select id="booking_type" name="booking_type">
                                        <?php foreach ($BOOKING_RULES as $key => $rule): ?>
                                            <option value="<?= e($key) ?>" <?= old_raw('booking_type', 'hourly') === $key ? 'selected' : '' ?>><?= e($rule['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="cd-field">
                                    <label for="service_address">Service address</label>
                                    <input id="service_address" name="service_address" type="text" maxlength="255"
                                        value="<?= old('service_address', $me['address']) ?>" placeholder="Where the service will take place" required>
                                </div>

                                <div class="cd-field">
                                    <label for="booking_date">Date</label>
                                    <input id="booking_date" name="booking_date" type="date" value="<?= old('booking_date') ?>" required>
                                </div>

                                <div class="cd-field">
                                    <label for="start_time">Start time</label>
                                    <input id="start_time" name="start_time" type="time" value="<?= old('start_time') ?>" required>
                                </div>

                                <div class="cd-field">
                                    <label for="end_time">End time</label>
                                    <input id="end_time" name="end_time" type="time" value="<?= old('end_time') ?>" required>
                                </div>

                                <div class="cd-field">
                                    <label for="emergency_contact_name">Emergency contact name (optional)</label>
                                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" maxlength="120" value="<?= old('emergency_contact_name', $me['emergency_contact_name']) ?>">
                                </div>

                                <div class="cd-field">
                                    <label for="emergency_contact_number">Emergency contact number (optional)</label>
                                    <input id="emergency_contact_number" name="emergency_contact_number" type="tel" maxlength="20" value="<?= old('emergency_contact_number', $me['emergency_contact_number']) ?>">
                                </div>

                                <div class="cd-field">
                                    <label for="children_info">Age and special needs (child or senior)</label>
                                    <input id="children_info" name="children_info" type="text" maxlength="255"
                                        value="<?= old('children_info') ?>"
                                        placeholder="e.g. Mia, 3 yrs, allergic to peanuts / Lola, 78 yrs, uses a walker">
                                </div>

                                <div class="cd-field">
                                    <label for="pet_info">Pet information</label>
                                    <input id="pet_info" name="pet_info" type="text" maxlength="255"
                                        value="<?= old('pet_info') ?>" placeholder="If this is a pet care booking">
                                </div>

                                <div class="cd-field cd-wide">
                                    <label for="special_instructions">Special instructions</label>
                                    <textarea id="special_instructions" name="special_instructions" maxlength="2000"
                                        placeholder="Anything the Provider should know"><?= old('special_instructions') ?></textarea>
                                </div>

                            </div>

                            <div class="cd-cost">
                                <div class="cd-cost-row"><span>Rate per hour</span><span id="cost-rate"><?= money($selSvc['hourly_rate'] ?? 0) ?></span></div>
                                <div class="cd-cost-row"><span>Billable hours</span><span id="cost-hours">0 hours</span></div>
                                <div class="cd-cost-row cd-cost-total"><span>Estimated total</span><span id="cost-total">₱0.00</span></div>
                                <p class="cd-hint" id="rule-hint"></p>
                                <p class="cd-hint" id="time-error" style="color:var(--err)" hidden></p>
                                <p class="cd-hint">Payment is made face-to-face with the Provider.</p>
                            </div>

                            <div class="cd-actions">
                                <button class="cd-btn" type="submit" <?= $selSvc ? '' : 'disabled' ?>>Send booking request</button>
                            </div>

                        </form>

                    </div>

                </section>


                <!-- ================= COST SUMMARY ================= -->
                <section class="cd-view" id="cost">

                    <div class="cd-view-head">
                        <h2>Cost Summary</h2>
                        <p>The cost of each booking, based on the Provider's rate and the duration.</p>
                    </div>

                    <div class="cd-stats cd-stats-3 row g-3">
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-4">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-card" />
                                </svg></div>
                            <div><strong><?= money($sumConfirmed) ?></strong><span>Confirmed (upcoming)</span></div>
                        </div>
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-4">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-card" />
                                </svg></div>
                            <div><strong><?= money($sumCompleted) ?></strong><span>Completed services</span></div>
                        </div>
                        <div class="cd-card cd-stat col-12 col-sm-6 col-xl-4">
                            <div class="cd-stat-icon"><svg class="cd-icon" aria-hidden="true">
                                    <use href="#i-card" />
                                </svg></div>
                            <div><strong><?= money($sumPending) ?></strong><span>Pending (not yet confirmed)</span></div>
                        </div>
                    </div>

                    <div class="cd-card">

                        <h3 class="cd-card-title">Cost per booking</h3>

                        <div class="cd-table-wrap table-responsive">
                            <table class="cd-table table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Provider</th>
                                        <th>Service</th>
                                        <th>Rate / hour</th>
                                        <th>Hours</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$allBookings): ?>
                                        <tr>
                                            <td class="cd-table-empty" colspan="6">No costs to show yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($allBookings as $b): ?>
                                        <tr>
                                            <td><?= e($b['pfirst'] . ' ' . $b['plast']) ?></td>
                                            <td><?= e($b['category']) ?></td>
                                            <td><?= money($b['hourly_rate']) ?></td>
                                            <td><?= e(fmt_hours($b['duration_hours'])) ?></td>
                                            <td><?= money($b['total_cost']) ?></td>
                                            <td><span class="cd-badge <?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="cd-hint">Payment is made face-to-face. I'll BeCare does not collect payments online.</p>

                    </div>

                </section>


                <!-- ================= REVIEWS ================= -->
                <section class="cd-view" id="reviews">

                    <div class="cd-view-head">
                        <h2>Reviews</h2>
                        <p>Rate a Provider after a completed service.</p>
                    </div>

                    <div class="cd-grid-2">

                        <div class="cd-card">
                            <h3 class="cd-card-title">Rate a service</h3>

                            <form class="cd-form" method="post" action="client_dashboard.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="submit_review">

                                <div class="cd-field">
                                    <label for="rv_booking">Completed booking</label>
                                    <select id="rv_booking" name="booking_id" required>
                                        <option value="">Choose a completed booking</option>
                                        <?php foreach ($reviewable as $b): ?>
                                            <option value="<?= (int)$b['booking_id'] ?>" <?= (int)old_raw('booking_id') === (int)$b['booking_id'] ? 'selected' : '' ?>>
                                                <?= e($b['pfirst'] . ' ' . $b['plast'] . ' — ' . $b['category'] . ' — ' . date('M j, Y', strtotime($b['start_datetime']))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="cd-field">
                                    <label for="rating">Rating</label>
                                    <select id="rating" name="rating" required>
                                        <option value="">Choose a rating</option>
                                        <?php foreach ([5 => 'Excellent', 4 => 'Very good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'] as $v => $lbl): ?>
                                            <option value="<?= $v ?>" <?= (int)old_raw('rating') === $v ? 'selected' : '' ?>><?= $v ?> - <?= $lbl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="cd-field">
                                    <label for="comment">Your experience</label>
                                    <textarea id="comment" name="comment" maxlength="1000" placeholder="Share how the service went"><?= old('comment') ?></textarea>
                                </div>

                                <div class="cd-actions">
                                    <button class="cd-btn" type="submit" <?= $reviewable ? '' : 'disabled' ?>>Submit review</button>
                                </div>
                            </form>
                        </div>

                        <div class="cd-card">
                            <h3 class="cd-card-title">My reviews</h3>

                            <?php if (!$myReviews): ?>
                                <div class="cd-empty">
                                    <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                        <use href="#i-star" />
                                    </svg>
                                    <strong>No reviews yet</strong>
                                    <span>Reviews you submit will appear here.</span>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($myReviews as $rv): ?>
                                <div class="cd-note">
                                    <span class="cd-dot"></span>
                                    <div>
                                        <strong><?= e($rv['first_name'] . ' ' . $rv['last_name']) ?> · <?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?></strong>
                                        <?php if (!empty($rv['comment'])): ?><p><?= e($rv['comment']) ?></p><?php endif; ?>
                                        <span class="cd-muted"><?= e(fmt_dt($rv['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>

                </section>


                <!-- ================= REPORTS ================= -->
                <section class="cd-view" id="reports">

                    <div class="cd-view-head">
                        <h2>Reports</h2>
                        <p>Report a Provider or a service concern. The Administrator will review it.</p>
                    </div>

                    <div class="cd-grid-2">

                        <div class="cd-card">
                            <h3 class="cd-card-title">Submit a report</h3>

                            <form class="cd-form" method="post" action="client_dashboard.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="submit_report">

                                <div class="cd-field">
                                    <label for="rp_booking">Related booking (optional)</label>
                                    <select id="rp_booking" name="booking_id">
                                        <option value="">Not about a specific booking</option>
                                        <?php foreach ($allBookings as $b): ?>
                                            <option value="<?= (int)$b['booking_id'] ?>" <?= (int)old_raw('booking_id') === (int)$b['booking_id'] ? 'selected' : '' ?>>
                                                <?= e($b['pfirst'] . ' ' . $b['plast'] . ' — ' . $b['category'] . ' — ' . date('M j, Y', strtotime($b['start_datetime']))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="cd-field">
                                    <label for="report_type">Type of report</label>
                                    <select id="report_type" name="report_type">
                                        <option value="complaint" <?= old_raw('report_type') === 'complaint' ? 'selected' : '' ?>>Complaint</option>
                                        <option value="safety_concern" <?= old_raw('report_type') === 'safety_concern' ? 'selected' : '' ?>>Safety concern</option>
                                        <option value="other" <?= old_raw('report_type') === 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>

                                <div class="cd-field">
                                    <label for="description">What happened?</label>
                                    <textarea id="description" name="description" maxlength="2000" required
                                        placeholder="Describe the issue in as much detail as you can"><?= old('description') ?></textarea>
                                </div>

                                <div class="cd-actions">
                                    <button class="cd-btn" type="submit">Send report</button>
                                </div>
                            </form>
                        </div>

                        <div class="cd-card">
                            <h3 class="cd-card-title">My reports</h3>

                            <?php if (!$myReports): ?>
                                <div class="cd-empty">
                                    <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                        <use href="#i-flag" />
                                    </svg>
                                    <strong>No reports filed</strong>
                                    <span>Status: Open, Investigating, Resolved, or Dismissed.</span>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($myReports as $rp): ?>
                                <div class="cd-note">
                                    <span class="cd-dot"></span>
                                    <div>
                                        <strong><?= e(ucwords(str_replace('_', ' ', $rp['report_type']))) ?>
                                            <?= $rp['first_name'] ? ' · ' . e($rp['first_name'] . ' ' . $rp['last_name']) : '' ?></strong>
                                        <p><?= e(mb_strimwidth($rp['description'], 0, 160, '…')) ?></p>
                                        <span class="cd-badge <?= e($rp['status']) ?>"><?= e(ucfirst($rp['status'])) ?></span>
                                        <span class="cd-muted"><?= e(fmt_dt($rp['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>

                </section>


                <!-- ================= NOTIFICATIONS ================= -->
                <section class="cd-view" id="notifications">

                    <div class="cd-view-head">
                        <h2>Notifications</h2>
                        <p>Booking confirmations, cancellations, reminders, and announcements.</p>
                    </div>

                    <div class="cd-card">

                        <div class="cd-card-head">
                            <h3 class="cd-card-title">All notifications</h3>
                            <form id="mark-read-form" method="post" action="client_dashboard.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_all_read">
                                <button class="cd-btn cd-btn-outline cd-btn-sm" type="submit" <?= $unread ? '' : 'disabled' ?>>Mark all as read</button>
                            </form>
                        </div>

                        <?php if (!$notes): ?>
                            <div class="cd-empty">
                                <svg class="cd-icon cd-empty-icon" aria-hidden="true">
                                    <use href="#i-bell" />
                                </svg>
                                <strong>No notifications yet</strong>
                                <span>You'll be told here when a Provider responds to a request.</span>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($notes as $n): ?>
                            <div class="cd-note <?= $n['is_read'] ? '' : 'is-unread' ?>">
                                <span class="cd-dot"></span>
                                <div>
                                    <strong><?= e($n['title']) ?></strong>
                                    <p><?= e($n['message']) ?></p>
                                    <span class="cd-muted"><?= e(fmt_dt($n['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>

                </section>


                <!-- ================= PROFILE ================= -->
                <section class="cd-view" id="profile">

                    <div class="cd-view-head">
                        <h2>My Profile</h2>
                        <p>Keep your details and emergency contact up to date.</p>
                    </div>

                    <div class="cd-card">

                        <h3 class="cd-card-title">Personal details</h3>
                        <?php
                        $profileAddressParts = array_map(
                            'trim',
                            explode(',', (string)($me['address'] ?? ''))
                        );
                        $profileMunicipality = '';
                        $profileBarangay = '';
                        if (
                            count($profileAddressParts) === 3 &&
                            $profileAddressParts[2] === 'Siquijor'
                        ) {
                            $profileBarangay = $profileAddressParts[0];
                            $profileMunicipality = $profileAddressParts[1];
                        }
                        ?>

                        <form class="cd-form" method="post" action="client_dashboard.php" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_profile">

                            <div class="cd-profile-photo-field">
                                <div class="cd-profile-photo-preview" aria-hidden="true">
                                    <?php if (!empty($me['photo'])): ?>
                                        <img src="../<?= e($me['photo']) ?>" alt="">
                                    <?php else: ?>
                                        <?= e(mb_strtoupper(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="cd-field">
                                    <label for="profile_photo">Profile picture</label>
                                    <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp">
                                    <small>Choose a JPG, PNG, or WebP image up to 5 MB. Leave blank to keep your current picture.</small>
                                </div>
                            </div>

                            <div class="cd-row cd-three">
                                <div class="cd-field">
                                    <label for="first_name">First name</label>
                                    <input id="first_name" name="first_name" type="text" maxlength="50" value="<?= old('first_name', $me['first_name']) ?>" required>
                                </div>
                                <div class="cd-field">
                                    <label for="middle_name">Middle name (optional)</label>
                                    <input id="middle_name" name="middle_name" type="text" maxlength="50" value="<?= old('middle_name', $me['middle_name']) ?>">
                                </div>
                                <div class="cd-field">
                                    <label for="last_name">Last name</label>
                                    <input id="last_name" name="last_name" type="text" maxlength="50" value="<?= old('last_name', $me['last_name']) ?>" required>
                                </div>
                            </div>

                            <div class="cd-row">
                                <div class="cd-field">
                                    <label for="email">Email address</label>
                                    <input id="email" type="email" maxlength="150" value="<?= e($me['email']) ?>" readonly>
                                </div>
                                <div class="cd-field">
                                    <label for="mobile_number">Mobile number</label>
                                    <input id="mobile_number" name="mobile_number" type="tel" maxlength="20" value="<?= old('mobile_number', $me['mobile_number']) ?>" required>
                                </div>
                            </div>

                            <div class="cd-row cd-three">
                                <div class="cd-field">
                                    <label for="profile_province">Home province</label>
                                    <input id="profile_province" type="text" value="Siquijor" readonly>
                                </div>
                                <div class="cd-field">
                                    <label for="profile_municipality">Home municipality</label>
                                    <select id="profile_municipality" name="municipality"
                                        data-selected="<?= e(old_raw('municipality', $profileMunicipality)) ?>" required>
                                        <option value="">Select municipality</option>
                                    </select>
                                </div>
                                <div class="cd-field">
                                    <label for="profile_barangay">Home barangay</label>
                                    <select id="profile_barangay" name="barangay"
                                        data-selected="<?= e(old_raw('barangay', $profileBarangay)) ?>" required disabled>
                                        <option value="">Select municipality first</option>
                                    </select>
                                </div>
                            </div>

                            <div class="cd-row">
                                <div class="cd-field">
                                    <label for="emergency_contact_name">Emergency contact name</label>
                                    <input id="emergency_contact_name" name="emergency_contact_name" type="text" maxlength="120" value="<?= old('emergency_contact_name', $me['emergency_contact_name']) ?>" required>
                                </div>
                                <div class="cd-field">
                                    <label for="emergency_contact_number">Emergency contact number</label>
                                    <input id="emergency_contact_number" name="emergency_contact_number" type="tel" maxlength="20" value="<?= old('emergency_contact_number', $me['emergency_contact_number']) ?>" required>
                                </div>
                            </div>

                            <div class="cd-actions">
                                <button class="cd-btn" type="submit">Save changes</button>
                            </div>
                        </form>

                    </div>

                    <div class="cd-card">

                        <h3 class="cd-card-title">Change password</h3>

                        <form class="cd-form" method="post" action="client_dashboard.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="cd-row cd-three">
                                <div class="cd-field">
                                    <label for="current_password">Current password</label>
                                    <input id="current_password" name="current_password" type="password" maxlength="72" autocomplete="current-password" required>
                                </div>
                                <div class="cd-field">
                                    <label for="new_password">New password</label>
                                    <input id="new_password" name="new_password" type="password" maxlength="72" autocomplete="new-password" placeholder="At least 8 characters" required>
                                </div>
                                <div class="cd-field">
                                    <label for="confirm_password">Confirm new password</label>
                                    <input id="confirm_password" name="confirm_password" type="password" maxlength="72" autocomplete="new-password" required>
                                </div>
                            </div>

                            <div class="cd-actions">
                                <button class="cd-btn" type="submit">Update password</button>
                            </div>
                        </form>

                    </div>

                    <div class="cd-card">
                        <h3 class="cd-card-title">Terms and Conditions</h3>
                        <p class="cd-muted">Review the terms that apply when using I'll BeCare.</p>
                        <button type="button" class="cd-btn cd-btn-outline" id="openClientTerms">
                            View Terms and Conditions
                        </button>
                    </div>

                    <div class="cd-terms-overlay" id="clientTermsModal" aria-hidden="true">
                        <section class="cd-terms-dialog" role="dialog" aria-modal="true"
                            aria-labelledby="clientTermsTitle">
                            <button type="button" class="cd-terms-close" id="closeClientTerms"
                                aria-label="Close Terms and Conditions">&times;</button>
                            <h2 id="clientTermsTitle">Terms and Conditions</h2>

                            <div class="cd-terms-content">
                                <h3>I'll BeCare Terms and Conditions</h3>
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
                            </div>
                        </section>
                    </div>

                </section>

            </main>

            <footer class="cd-footer">
                © 2026 I'll BeCare · Siquijor Island
            </footer>

        </div>

    </div>

    <script src="../Assets/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/siquijor_barangay.js"></script>
    <script src="../JS/client_dashboard.js?v=terms-focus-fix"></script>
</body>

</html>