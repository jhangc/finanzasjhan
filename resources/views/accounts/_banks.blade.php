<datalist id="banks">
    @foreach (['BCP', 'Interbank', 'BBVA', 'Scotiabank', 'BanBif', 'Banco Pichincha', 'Caja Arequipa', 'Caja Huancayo', 'Yape', 'Plin'] as $bank)
        <option value="{{ $bank }}">
    @endforeach
</datalist>
