<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Stripe response</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<div class="container">
		<div class="mt-3">
			<h1>Stripe response</h1>
			@if(\Session::has('error'))
				<div class="alert alert-danger" role="alert">{{ \Session::get('error') }}</div>
			@endif
			@if(\Session::has('success'))
				<div class="alert alert-success" role="alert">{{ \Session::get('success') }}</div>
			@endif
		</div>
	</div>
</body>