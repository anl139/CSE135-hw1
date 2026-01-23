Team:
Andrew Lam
Password for grader: anl160
[anl139.site](https://anl139.site/)
I found this guide to auto-deploy using [github webhooks](https://portent.com/blog/design-dev/github-auto-deploy-setup-guide.htm)
but essentially i create a ssh directory for user www-data then generated a key for it and copied it into the directory. I then deployed the key onto the github repository
and clone the repo onto the server and created a deployment script (deploy.php) in the site root with commands like git pull, git submodule update, etc. Then on github i created the webhook in the repo settings
in which i put the [link](https://anl139.site/deploy.php) and there it works

Username/password
andrew: Anny2001
sql
andrew: Anny2001
grader: anl160

what i notice in the html is that it is only half of the bytes as the original as when i curl onto the website content length is 2510 but when i go on chrome and see that it was compressed content length was 1065 more than 1/2 of the original size
Now for me to change the server header to "CSE 135" I first had to install a package called apache2mod-security in which i had to go to the security.conf file of apache/conf-avaiable and change the value of ServerTokens,Server Signature on and put a new
thing called SecServerSignature an add "CSE 135" with it for it to change
