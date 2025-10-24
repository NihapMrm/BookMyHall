<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>BookMyHall Dashboard</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="css/style.css" />
</head>
<body>
	<?php include __DIR__ . '/includes/sidebar.php'; ?>
	<?php include __DIR__ . '/includes/header.php'; ?>

	<main class="content-wrapper">
		<section class="dashboard-grid">
			<div class="stats-grid">
				<article class="stat-card">
					<div class="stat-card__header">
						<h3 class="stat-card__title">Total Bookings</h3>
						<span class="stat-card__icon">
							<i class="fa-solid fa-calendar-check"></i>
						</span>
					</div>
					<p class="stat-card__value">342</p>
					<span class="stat-card__trend trend-up">8.5% Up from last month</span>
				</article>

				<article class="stat-card">
					<div class="stat-card__header">
						<h3 class="stat-card__title">This Month</h3>
						<span class="stat-card__icon">
							<i class="fa-solid fa-calendar-days"></i>
						</span>
					</div>
					<p class="stat-card__value">28</p>
					<span class="stat-card__trend trend-up">12% Up from last month</span>
				</article>

				<article class="stat-card">
					<div class="stat-card__header">
						<h3 class="stat-card__title">Monthly Revenue</h3>
						<span class="stat-card__icon">
							<i class="fa-solid fa-sack-dollar"></i>
						</span>
					</div>
					<p class="stat-card__value">$8,450</p>
					<span class="stat-card__trend trend-up">15.3% Up from last month</span>
				</article>

				<article class="stat-card">
					<div class="stat-card__header">
						<h3 class="stat-card__title">Pending Requests</h3>
						<span class="stat-card__icon">
							<i class="fa-solid fa-clock"></i>
						</span>
					</div>
					<p class="stat-card__value">7</p>
					<span class="stat-card__trend trend-down">2 less than yesterday</span>
				</article>
			</div>

			<section class="chart-card">
				<header class="card-header">
					<h2>Booking Trends</h2>
					<select class="card-filter" aria-label="Select month">
						<option>October</option>
						<option>September</option>
						<option>August</option>
					</select>
				</header>
				<div class="chart-placeholder">Monthly booking trends chart</div>
			</section>

			<section class="deals-card">
				<header class="card-header">
					<h2>Recent Bookings</h2>
					<select class="card-filter" aria-label="Select month">
						<option>October</option>
						<option>September</option>
						<option>August</option>
					</select>
				</header>

				<table class="deal-table">
					<thead>
						<tr>
							<th scope="col">Customer Name</th>
							<th scope="col">Contact</th>
							<th scope="col">Event Date</th>
							<th scope="col">Duration</th>
							<th scope="col">Amount</th>
							<th scope="col">Status</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<div class="deal-item">
									<div class="customer-avatar">
										<i class="fa-solid fa-user"></i>
									</div>
									<div>
										<span class="customer-name">Sarah Johnson</span>
										<span class="event-type">Wedding Reception</span>
									</div>
								</div>
							</td>
							<td>+1 (555) 123-4567</td>
							<td>Nov 15, 2025 - 6:00 PM</td>
							<td>4 hours</td>
							<td>$1,200</td>
							<td><span class="deal-status status-confirmed">Confirmed</span></td>
						</tr>
						<tr>
							<td>
								<div class="deal-item">
									<div class="customer-avatar">
										<i class="fa-solid fa-user"></i>
									</div>
									<div>
										<span class="customer-name">Michael Chen</span>
										<span class="event-type">Corporate Event</span>
									</div>
								</div>
							</td>
							<td>+1 (555) 987-6543</td>
							<td>Nov 18, 2025 - 10:00 AM</td>
							<td>6 hours</td>
							<td>$2,800</td>
							<td><span class="deal-status status-pending">Pending</span></td>
						</tr>
						<tr>
							<td>
								<div class="deal-item">
									<div class="customer-avatar">
										<i class="fa-solid fa-user"></i>
									</div>
									<div>
										<span class="customer-name">Emma Rodriguez</span>
										<span class="event-type">Birthday Party</span>
									</div>
								</div>
							</td>
							<td>+1 (555) 456-7890</td>
							<td>Nov 22, 2025 - 2:00 PM</td>
							<td>5 hours</td>
							<td>$850</td>
							<td><span class="deal-status status-confirmed">Confirmed</span></td>
						</tr>
						<tr>
							<td>
								<div class="deal-item">
									<div class="customer-avatar">
										<i class="fa-solid fa-user"></i>
									</div>
									<div>
										<span class="customer-name">David Wilson</span>
										<span class="event-type">Anniversary</span>
									</div>
								</div>
							</td>
							<td>+1 (555) 321-0987</td>
							<td>Nov 25, 2025 - 7:30 PM</td>
							<td>3 hours</td>
							<td>$650</td>
							<td><span class="deal-status status-cancelled">Cancelled</span></td>
						</tr>
					</tbody>
				</table>
			</section>
		</section>
	</main>

	<script>
		const body = document.body;
		const toggle = document.querySelector('.sidebar__menu-toggle');

		if (toggle) {
			toggle.addEventListener('click', function () {
				body.classList.toggle('is-sidebar-open');
			});
		}
	</script>
</body>
</html>
