#!/usr/bin/perl
use strict;
use warnings;
use CGI;
use CGI::Session;
use CGI::Carp qw(fatalsToBrowser);

my $cgi = CGI->new;

my $sid = $cgi->cookie('CGISESSID');
my $session = CGI::Session->new(
    undef,
    $sid,
    { Directory => '/tmp' }
);

my $name = $session->param('username');

print $cgi->header(
    -type   => 'text/html',
    -cookie => $cgi->cookie(
        -name  => 'CGISESSID',
        -value => $session->id
    )
);

print <<HTML;
<html>
<head><title>Perl Sessions</title></head>
<body>
<h1>Perl Sessions Page 2</h1>
HTML

if ($name) {
    print "<p><b>Name:</b> $name</p>";
} else {
    print "<p><b>Name:</b> You do not have a name set</p>";
}

print <<HTML;
<br/><br/>
<a href="/cgi-bin/perl-sessions-1.pl">Session Page 1</a><br/>
<a href="/perl-cgiform.html">Perl CGI Form</a><br/>
<form style="margin-top:30px" action="/cgi-bin/perl-destroy-session.pl" method="get">
<button type="submit">Destroy Session</button>
</form>
</body>
</html>
HTML



