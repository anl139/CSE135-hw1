#!/usr/bin/perl
use strict;
use warnings;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use CGI::Session;

my $cgi = CGI->new;

my $session = CGI::Session->new(
    "driver:File",
    undef,
    { Directory => "/tmp" }
);

my $cookie = $cgi->cookie(
    -name  => 'CGISESSID',
    -value => $session->id
);

print $cgi->header( -cookie => $cookie );

my $name = $session->param('username') || $cgi->param('username');
$session->param( "username", $name );

print <<HTML;
<html>
<head><title>Perl Sessions</title></head>
<body>
<h1>Perl Sessions Page 1</h1>
HTML

if ($name) {
    print "<p><b>Name:</b> $name</p>";
} else {
    print "<p><b>Name:</b> You do not have a name set</p>";
}

print <<HTML;
<br/><br/>
<a href="/cgi-bin/perl-sessions-2.pl">Session Page 2</a><br/>
<a href="/perl-cgiform.html">Perl CGI Form</a><br/>
<form style="margin-top:30px" action="/cgi-bin/perl-destroy-session.pl" method="get">
<button type="submit">Destroy Session</button>
</form>
</body>
</html>
HTML


print "</body>";
print "</html>";
