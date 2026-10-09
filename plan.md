design style -> a clean modern saas enterprise ux/ui design
design rule
 - modern red theme pallete with modern black navy sidebar,white bg top nav
 -  top nave have a user avatar ,change password
 - white bg for main dynamic page all page have similar design 
 - use datatable reusable components , datatable design it have modern search input at top in right side have optional ui/componets to add like buttons ,below of the inline search and optional component like button is all filter dropdowns inline all horizontally below of it is the actual modern table then at the bottom is pagination and limit.
 - use all reusable components accross the system
 - all action is in modal and all action have confirmation if neccesary 
 - remark is optional
 - design the system based on the current database
 - use the image fro public/assets/image for login cover and app logo
 - use bootstrap for basic and dynamic changes but to enhance the ui use custom css all make sure to separate it by css file all css file must access through layout header also the js/jquery in footer 
 - use route on it ,follow the model,service,controller and view dont use rest api
 - code is simple ,easy to understand and track but efficient enough
 - avoid complicated /complex code design if not that neccesary
 - dashboard use chart.js for visual use only the needed
 - sidebar have this
     - Dashboard

     - System Documents
        - hardcopy documents 
        - softcopy documents
     - Request 
       - My Request
         - my request have tabs for softcopy request,hardcopy request,hardcopy transfer rquest,document access grant request,document assign request pages 

       - My task
       my task have tabs for softcopy request,hardcopy request,hardcopy transfer rquest,document access grant request,document assign request pages 

     - Places
        - places have tabs for area,specific,asset,location,sequence,softcopy-categories each tabs on pages

     - Administration
       - User management
       - Roles and permission
       - Workflow builder

- roles and permission rules and default
  role have staff,plant manager,document controll officer,internal audit,super_admin
  permission is by module and its module is have permission permission is based on user action or all actions and pages in system access
   permission must work both client side and server side

- fully build it based on the rules i give free to add your recommendatio 


 