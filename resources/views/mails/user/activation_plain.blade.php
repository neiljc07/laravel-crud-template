Hi {{ $activation_code->user->first_name }} {{ $activation_code->user->last_name }},

This is your activation code
{{ $activation_code->code }}