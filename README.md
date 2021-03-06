About
=====

I prefer Git to Subversion and have created a git-svn clone of the original repo. As changes are made to the SVN repo, I plan to merge and publish them here.

Branches
========

There are two branches included in this repository. The `Master` branch is simply a clone of the main SVN repo. The `Custom` branch contains all of the changes that I make to the software. Currently, most of my projects are developed in [CakePHP](http://cakephp.org), I have made changes to the configuration files to settings, etc. from a [CakePHP](http://cakephp.org) installation.

Custom Branch Configuration
---------------------------

I have altered the codebase so that I can place the ajaxplorer repo in the [CakePHP](http://cakephp.org) `Vendors` directory. This will work in both the `app/Vendors` and `vendors` directories.

This custom configuration assumes a `Users` controller with `login` and `logout` functions.

###Installation###

1. Clone this repository into the `Vendors` directory of your CakePHP application.
2. Create a symbolic link in your application's `webroot` directory pointing to the `src` folder in the repo.
	- `ln -s ../Vendors/ajaxplorer/core/src ajaxplorer`

###Implementation###

There are many ways to implement AjaxPlorer with CakePHP. There are many custom variables that can go into the authentication processes. The instructions below are a simple guide to get started.

If you are having trouble, then please do not create an issue request as the problem is almost certainly with your configuration and not an issue with this app. If you need assistance with [AjaxPlorer](http://ajaxplorer.info) or [CakePHP](http://cakephp.net), then please use the references that those tools provide.

1. Edit AjaxPlorer's Auth.remote plugin glueCode with a secret specific to your installation.
	* If you followed the instructions above, you can file this file in `webroot/ajaxplorer/plugins/auth.remote/glueCode.php`.
2. Edit your applications `bootstrap.php` file with the secret variable.
	* `Configure::write('Ajaxplorer.secret', 'secretFromGlueCode');`
3. Integrate Ajaxplorer remote code with CakePHP.
	* There are no easy answers to how to approach this step. You will have to reference the CakePHP and AjaxPlorer API's to figure out the integration based on how you have built your authentication system.
	* I have created an AjaxPlorer to CakePHP bridge component for CakePHP that can assist with the authentication. You can download it from it's [Github Repo](https://github.com/scottharwell/ajaxplorer-cakephp-bridge-component).
		* There are instructions with the repository on how to use this component. If you have a simple authentication mechanism, then you can simply add `$this->Ajaxplorer->login($user);` to your login function and your user will be logged in to both CakePHP and AjaxPlorer simultaneously.

License Information
===================

AjaxPlorer License
------------------

This program is published under the LGPL GNU Lesser General Public License. You should have received a copy of the license along with AjaXplorer.

The main conditions are as follows:

You must conspicuously and appropriately publish on each copy distributed an appropriate copyright notice and disclaimer of warranty and keep intact all the notices that refer to this License and to the absence of any warranty; and give any other recipients of the Program a copy of the GNU Lesser General Public License along with the Program.

If you modify your copy or copies of the library or any portion of it, you may distribute the resulting library provided you do so under the GNU Lesser General Public License. However, programs that link to the library may be licensed under terms of your choice, so long as the library itself can be changed. Any translation of the GNU Lesser General Public License must be accompanied by the GNU Lesser General Public License.

If you copy or distribute the program, you must accompany it with the complete corresponding machine-readable source code or with a written offer, valid for at least three years, to furnish the complete corresponding machine-readable source code.

Any of the above conditions can be waived if you get permission from the copyright holder. AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

Custom Updates
--------------

I have not applied any copyright to the changes I have made to the AjaxPlorer application. They should fall under the same license as the application itself.