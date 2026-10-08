application/
│
├── core/
│   ├── MY_Controller.php
│   └── MY_Model.php
│
├── modules/
│  │
│  ├── users/
│      ├── users_controller.php       ← Controller
│      ├── users_service.php          ← Business Logic
│      ├── users_model.php            ← Database
│      └── views/
│          ├── index.php              ← Main Page
│          └── modals/
│              ├── upsert.php         ← Create/Edit
│              ├── permissions.php    ← Custom Modal
│              └── reset_password.php ← Custom Modal
│   

│
├── libraries/
│   ├── Auth.php
│   └── Response.php
│
├── helpers/
│   └── common_helper.php
│
├── views/
│   └── layouts/
│       ├── header.php
│       ├── footer.php
│       └── sidebar.php
│
└── config/
    ├── autoload.php
    ├── config.php
    └── routes.php