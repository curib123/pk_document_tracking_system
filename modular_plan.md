application/
│
├── core/
│   ├── MY_Controller.php
│   ├── MY_Model.php
│   └── MY_Loader.php
│
├── config/
│   ├── autoload.php
│   ├── config.php
│   ├── routes.php
│   └── constants.php
│
├── libraries/
│   ├── Auth_service.php
│   ├── Response_service.php
│   ├── Validation_service.php
│   └── Upload_service.php
│
├── helpers/
│   ├── api_helper.php
│   ├── common_helper.php
│   └── permission_helper.php
│
├── modules/
│   │
│   ├── auth/
│   │   ├── controllers/
│   │   │   └── Auth.php
│   │   ├── models/
│   │   │   └── Auth_model.php
│   │   ├── services/
│   │   │   └── Auth_service.php
│   │   ├── views/
│   │   │   └── login.php
│   │   └── config/
│   │       └── routes.php
│   │
│   ├── users/
│   │   ├── controllers/
│   │   │   └── Users.php
│   │   ├── models/
│   │   │   └── User_model.php
│   │   ├── services/
│   │   │   └── User_service.php
│   │   ├── requests/
│   │   │   └── User_request.php
│   │   └── views/
│   │       ├── index.php
│   │       ├── create.php
│   │       └── edit.php
│   │
│   ├── products/
│   │   ├── controllers/
│   │   ├── models/
│   │   ├── services/
│   │   ├── requests/
│   │   └── views/
│   │
│   └── reports/
│       ├── controllers/
│       ├── models/
│       ├── services/
│       └── views/
│
├── repositories/
│   ├── User_repository.php
│   ├── Product_repository.php
│   └── Report_repository.php
│
├── services/
│   ├── Base_service.php
│   ├── Transaction_service.php
│   └── Notification_service.php
│
├── models/
│   └── Base_model.php
│
├── views/
│   ├── layouts/
│   │   ├── main.php
│   │   ├── auth.php
│   │   └── admin.php
│   └── errors/
│
└── third_party/