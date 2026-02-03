#!/usr/bin/perl
use strict;
use warnings;
use CGI;
use CGI::Session;
use CGI::Carp qw(fatalsToBrowser);

my $cgi = CGI->new;
my $sid = $cgi->cookie('CGISESSID');

my $session;
if ($sid) {
    $session = CGI::Session->new(
        undef,
        $sid,
        { Directory => '/tmp' }
    );
    $session->delete;
}

# Expire cookie in browser
my $expired_cookie = $cgi->cookie(
    -name    => 'CGISESSID',
    -value   => '',
    -expires => '-1d'
);

print $cgi->header(
    -type   => 'text/html',
    -cookie => $expired_cookie
);

print <<HTML;
<html>
<head><title>Perl Session Destroyed</title></head>
<body>
<h1>Session Destroyed</h1>
<a href="/perl-cgiform.html">Back to the Perl CGI Form</a><br />
<a href="/cgi-bin/perl-sessions-1.pl">Back to Page 1</a><br />
<a href="/cgi-bin/perl-sessions-2.pl">Back to Page 2</a>
</body>
</html>
HTML
