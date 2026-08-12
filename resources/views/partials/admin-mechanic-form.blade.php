@php $editing = $mechanic !== null; @endphp
<form class="dialog-form" method="POST" action="{{ $editing ? route('admin.mechanics.update', $mechanic) : route('admin.mechanics.store') }}">
    @csrf @if($editing) @method('PATCH') @endif
    <div class="form-grid">
        <label><span>English name</span><input name="name_en" value="{{ $mechanic?->name_en }}" required></label>
        <label><span>Arabic name</span><input name="name_ar" value="{{ $mechanic?->name_ar }}" dir="rtl" required></label>
        <label><span>Email</span><input name="email" type="email" value="{{ $mechanic?->email }}"></label>
        <label><span>Phone</span><input name="phone" value="{{ $mechanic?->phone }}"></label>
        <label class="full"><span>Address</span><input name="address" value="{{ $mechanic?->address }}" required></label>
        <label><span>City</span><input name="city" value="{{ $mechanic?->city }}" required></label>
        <label><span>Country</span><input name="country_code" maxlength="2" value="{{ $mechanic?->country_code ?? 'EG' }}" required></label>
        <label><span>Latitude</span><input name="latitude" type="number" step="0.0000001" value="{{ $mechanic?->latitude ?? '30.0444' }}" required></label>
        <label><span>Longitude</span><input name="longitude" type="number" step="0.0000001" value="{{ $mechanic?->longitude ?? '31.2357' }}" required></label>
        <label class="full"><span>Specialties</span><select name="specialty_codes[]" multiple size="4">@foreach($mechanicSpecialties as $specialty)<option value="{{ $specialty->code }}" @selected($mechanic?->specialties?->contains('id', $specialty->id))>{{ $specialty->name_en }}</option>@endforeach</select></label>
    </div>
    <div class="check-grid"><label class="check-row"><input type="hidden" name="verified" value="0"><input type="checkbox" name="verified" value="1" @checked($mechanic?->verified)><span><strong>Verified</strong><small>Visible as a trusted workshop.</small></span></label><label class="check-row"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($mechanic?->active ?? true)><span><strong>Active</strong><small>Available in customer search.</small></span></label></div>
    <div class="dialog-actions"><button type="button" class="admin-button secondary" data-close-dialog>Cancel</button><button class="admin-button primary" type="submit">{{ $editing ? 'Save mechanic' : 'Create mechanic' }}</button></div>
</form>
