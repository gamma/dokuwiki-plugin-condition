====== Condition Plugin for DokuWiki ======

Use :

	<if [condition_list]>doku code</if>
	or <if [condition_list]>doku code<else>doku code</if>
	
	[condition_list] is a set of [condition] records separated by logical operators (&&, and, ||, or, ^, xor for now), use of parenthesis is allowed, negation is achieved by using heading ! (ex !foo=bar or !(a=b || c<d) )
	
	[condition] is formed from a [key], followed by an [operator] (optionnal) and then a [value] (optionnal)
	
	[key] is in the list (defined in base_tester.php as of 2009/06/10) :
		- user : refers to the user "login" (like in $_SERVER['REMOTE_USER'])
		- group : refers to the user group-set
		- nsread : refers to the ability of the user to read a namespace
		- nsedit : refers to the ability of the user to edit a namespace
		- IP : refers to the client's IP address
	
	[operator] signification is [key] dependent, for example :
		- = (==) : equality, membership, read/edit ability on ...
		- != : non-equality, non-member, no read/edit ability on ...
		- ...
		(browse base_tester.php for test_* methods for more information)
	
	[value] can be a string (whitespace, ) and > free) or a " delimited string (whitespaces, ) and > are then allowed)
	
All documentation for the User Subscriptions Plugin is available online at:
http://wiki.splitbrain.org/plugin:condition

(c) 2009 by Etienne Meleard <etienne.meleard@free.fr>, (c) 2013 by Gerry Weißbach <gweissbach@inetsoftware.de> See COPYING for license info.
