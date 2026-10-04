@props(['name', 'show' => false, 'maxWidth' => '2xl'])
<div class="modal fade" id="{{ $name }}" tabindex="-1" aria-labelledby="{{ $name }}-titulo" aria-hidden="true" data-blh-modal="{{ $name }}" data-show="{{ $show ? 'true' : 'false' }}">
    <div class="modal-dialog modal-dialog-centered {{ in_array($maxWidth, ['xl', '2xl']) ? 'modal-lg' : '' }}">
        <div class="modal-content"><div class="modal-body">{{ $slot }}</div></div>
    </div>
</div>
