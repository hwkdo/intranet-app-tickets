<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $kategorie }}</title>
</head>
<body>
    <h2>Ticket {{ $kategorie }}</h2>
    <table>
        <tr><td>Betreff:</td><td>{{ $betreff }}</td></tr>
        <tr><td>Organisation:</td><td>Handwerkskammer Dortmund</td></tr>
        <tr><td>Kunde:</td><td>{{ $name }}</td></tr>
        <tr><td>E-Mail:</td><td>{{ $email }}</td></tr>
    </table>
    <h3>Meldung:</h3>
    <div style="white-space: pre-wrap;">{{ $inhalt }}</div>
    <p>Dies ist eine automatisch generierte Mail (Intranet).</p>
</body>
</html>
