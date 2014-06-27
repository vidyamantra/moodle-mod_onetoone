
One-to-one module for Moodle

This program is free software: you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option)
any later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
-----------------------------------------------------------------------------


Description
------------

One-to-one activities are used to schedule whiteboard session which
require advance booking.

Each activity is offered in one or more identical sessions.  

Reminder messages are sent to users and their managers a few days before the
session is scheduled to start.  Confirmation messages are sent when users
sign-up for a session or cancel.


Requirements
-------------

* Moodle 2.6.0+



Installation
-------------

1- Unpack the "moodle-mod_onetoone.zip" and rename that unzipped folder to "onetoone" and  place this folder into 'mod' directory of moodle.
   The file structure for ontoone would be something like. 
	[site-root]/mod/onetoone
    
	
2- Dependencies
--------------
-> To run "onetoone" module you need to add another plugin named "getkey" found at "https://github.com/vidyamantra/moodle-local_getkey".
Locate this folder 'getkey' into 'local' directory of moodle 

File structure for getkey would be.
[site-root]/local/getkey


3- Visit the admin notification page to trigger the database installation by
	[site-root] > Site administration > Notifications



Bugs/patches
-------------

Feel free to send bug reports (and/or patches!) to the 

Current maintainer:-

Suman Bogati <suman@vidyamantra.com>

Previous maintainer:-

  Pinky Sharma <pinky@vidyamantra.com>
