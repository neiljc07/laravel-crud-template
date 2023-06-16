<p>Hi {{ $activation_code->user->first_name }} {{ $activation_code->user->last_name }},</p>

<div style="text-align: center">
  <h3>This is your activation code</h3>

  <h2>{{ $activation_code->code }}</h2>
</div>