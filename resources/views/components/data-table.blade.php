<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">
    <thead>
    <tr>
        @foreach(array_keys($data[0] ?? []) as $header)
            <th style="background:#f9f9f9;text-align:left;">{{ ucwords(str_replace('_',' ', $header)) }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $row)
        <tr>
            @foreach($row as $col)
                <td>{{ $col }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
