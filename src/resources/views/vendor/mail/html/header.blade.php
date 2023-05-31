<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'ECPay')
            <!-- <img src="{{ asset('/images') }}" class="logo" alt="LIBERTY COMMERCIAL CENTER"> -->
            <h1>LIBERTY COMMERCIAL CENTER</h1>
            @else
            {{ $slot }}
            @endif
        </a>
    </td>
</tr>