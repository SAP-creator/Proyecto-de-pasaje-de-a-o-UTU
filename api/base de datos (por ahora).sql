Create Database DB;
Use DB;

Create Table usuario(
    cedula INT(9) NOT NULL, PRIMARY KEY (cedula),
    clave TEXT(20),
    tipo ENUM("vecino","camionero","municipal","admin")
);

Create Table solicitud_usuario(
    cedula INT(9) NOT NULL, PRIMARY KEY(cedula),
    clave TEXT(20),
    tipo ENUM("vecino","camionero","municipal","admin")
);

Create Table trabajador (
    nombre VARCHAR(100),
    apellido VARCHAR(100),
    cedula  INT(9),
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES usuario(cedula)
);

Create Table vecino (
    cedula  INT(9) NOT NULL, PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES usuario (cedula)
);

Create Table admin_sistemas (
    cedula  INT(9) NOT NULL, PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador (cedula)
);

Create Table operador (
    cedula  INT(9) NOT NULL, PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador (cedula)
);

Create Table admin_municipal_operador (
    cedula  INT(9) NOT NULL, PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador (cedula)
);
Create Table admin_municipal_general (
    cedula  INT(9) NOT NULL, PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador (cedula)
);