## Team
- **Andrew Lam**
---
## Server & Credentials
**Server IP:** `167.172.120.163`
### Grader Account
- **Username:** grader  
- **Password:** anl160

### User Account
- **Username:** andrew  
- **Password:** Anny2001

### Database Login
- **DB Name:** collector_db  
- **User:** collector_andrew  
- **Table:** logs
### Analytics Login
**Super Admin**
- Username: superadmin
- Password: superpw
- 
**Analyst**
- Username: admin
- Password: password
- Username: sam
- Password: sam123
- Username: sally
- Password: sally123

**Viewer**
- Username: viewer1
- Password: viewer123

## Streamline Process one could take
- **Reporting site**
- Go to [https://reporting.anl139.site/reports](https://reporting.anl139.site/reports)
  - could try [https://reporting.anl139.site/admin](https://reporting.anl139.site/admin) still leads to [https://reporting.anl139.site/login](https://reporting.anl139.site/login)
- use any of the analytics login(superadmin for full access)
- from there view each of the tabs and attempt to export the respective table into pdf
- if using a analyst/superadmin have the option to add a comment onto the table before it downloads to a pdf
- if using the superadmin account click on "User admin" to navigate the admin dashboard
- from there you can add/delete users or change passwords for the existing accounts
- you can log out and test the new changes or go back to report to see the reports again
- **Testing Site**
- this uses the template provided by professor so go to [https://test.anl139.site/](https://test.anl139.site/)
- do some actions there and from there grader can go back to reporting site and see recent changes
- **Main Site**
- go to [https://anl139.site/](https://anl139.site/)
- click around
- to test the echo to the echo form
- uh for some reason get and post works but put and delete gives an error i never figured out how to fix that
- disabling javascript will only allow get/post
- **collector**
- to test this I primarily did curls of each command like curl -X GET https://reporting.anl139.site/api/logs and see that it returned my data
- same thing with post put and delete in which to test if it works we could go to the database to see if it populated correctly
### Concerns
- on the main site in terms of implementing the form for the echo, I noticed that even when I chmod the respective files to work after a few weeks it gets disabled and states that it isnt accessible i never found the reason for that
- the solutition i had was to manually sudo chmod 755 *.cgi / py/ php etc to update them
- on the reporting website my main concern was that I wasnt really sure how to split up the data to three main pieces since I felt some of them(like mouse movements) where too large to properly represent causing my data to feel "baren"
- one bug I couldnt resolve was the fact that for the tables I wanted it so that a user can click a next button to generate the next few reports in the 50 i set out but always had bugs and had to abandon it
- in which a major concern I have was that with the usage of Javascript, the site breaks down should we disable javascript, things dont update and not much interactivity and havent found the time to fix it
