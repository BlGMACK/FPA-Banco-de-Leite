@props(['align' => 'right', 'width' => '48', 'contentClasses' => ''])
<div class="dropdown" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = !open">{{ $trigger }}</div>
    <div class="dropdown-menu {{ $align === 'right' ? 'dropdown-menu-end' : '' }}" :class="{ 'show': open }" @click="open = false">{{ $content }}</div>
</div>
