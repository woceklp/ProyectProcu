USE [master]
GO
/****** Object:  Database [SistemaGestorTramitesAgrarios]    Script Date: 23/04/2025 10:59:27 p. m. ******/
CREATE DATABASE [SistemaGestorTramitesAgrarios]
 CONTAINMENT = NONE
 ON  PRIMARY 
( NAME = N'SistemaGestorTramitesAgrarios', FILENAME = N'C:\Program Files\Microsoft SQL Server\MSSQL16.MSSQLSERVER\MSSQL\DATA\SistemaGestorTramitesAgrarios.mdf' , SIZE = 73728KB , MAXSIZE = UNLIMITED, FILEGROWTH = 65536KB )
 LOG ON 
( NAME = N'SistemaGestorTramitesAgrarios_log', FILENAME = N'C:\Program Files\Microsoft SQL Server\MSSQL16.MSSQLSERVER\MSSQL\DATA\SistemaGestorTramitesAgrarios_log.ldf' , SIZE = 8192KB , MAXSIZE = 2048GB , FILEGROWTH = 65536KB )
 WITH CATALOG_COLLATION = DATABASE_DEFAULT, LEDGER = OFF
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET COMPATIBILITY_LEVEL = 160
GO
IF (1 = FULLTEXTSERVICEPROPERTY('IsFullTextInstalled'))
begin
EXEC [SistemaGestorTramitesAgrarios].[dbo].[sp_fulltext_database] @action = 'enable'
end
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ANSI_NULL_DEFAULT OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ANSI_NULLS OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ANSI_PADDING OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ANSI_WARNINGS OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ARITHABORT OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET AUTO_CLOSE ON 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET AUTO_SHRINK OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET AUTO_UPDATE_STATISTICS ON 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET CURSOR_CLOSE_ON_COMMIT OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET CURSOR_DEFAULT  GLOBAL 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET CONCAT_NULL_YIELDS_NULL OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET NUMERIC_ROUNDABORT OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET QUOTED_IDENTIFIER OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET RECURSIVE_TRIGGERS OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET  ENABLE_BROKER 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET AUTO_UPDATE_STATISTICS_ASYNC OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET DATE_CORRELATION_OPTIMIZATION OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET TRUSTWORTHY OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ALLOW_SNAPSHOT_ISOLATION OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET PARAMETERIZATION SIMPLE 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET READ_COMMITTED_SNAPSHOT OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET HONOR_BROKER_PRIORITY OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET RECOVERY SIMPLE 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET  MULTI_USER 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET PAGE_VERIFY CHECKSUM  
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET DB_CHAINING OFF 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET FILESTREAM( NON_TRANSACTED_ACCESS = OFF ) 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET TARGET_RECOVERY_TIME = 60 SECONDS 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET DELAYED_DURABILITY = DISABLED 
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET ACCELERATED_DATABASE_RECOVERY = OFF  
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET QUERY_STORE = ON
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET QUERY_STORE (OPERATION_MODE = READ_WRITE, CLEANUP_POLICY = (STALE_QUERY_THRESHOLD_DAYS = 30), DATA_FLUSH_INTERVAL_SECONDS = 900, INTERVAL_LENGTH_MINUTES = 60, MAX_STORAGE_SIZE_MB = 1000, QUERY_CAPTURE_MODE = AUTO, SIZE_BASED_CLEANUP_MODE = AUTO, MAX_PLANS_PER_QUERY = 200, WAIT_STATS_CAPTURE_MODE = ON)
GO
USE [SistemaGestorTramitesAgrarios]
GO
/****** Object:  UserDefinedFunction [dbo].[CalcularDiasParaReiteracion]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

CREATE   FUNCTION [dbo].[CalcularDiasParaReiteracion] (
    @idTramite INT
)
RETURNS INT
AS
BEGIN
    DECLARE @diasTranscurridos INT = 0;
    DECLARE @estadoBasico INT;
    DECLARE @statusReal VARCHAR(20);
    DECLARE @fechaInicio DATE;
    
    -- Obtener el estado básico actual y estado real
    SELECT 
        @estadoBasico = t.ID_EstadoBasico,
        @statusReal = CASE 
            WHEN EXISTS (
                SELECT 1 FROM Acuses a 
                WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico = 2
            ) THEN 'PREVENIDO'
            WHEN (
                NOT EXISTS (
                    SELECT 1 FROM Acuses a 
                    WHERE a.ID_Tramite = t.ID_Tramite AND a.ID_EstadoBasico <> 3
                ) AND EXISTS (
                    SELECT 1 FROM Acuses a 
                    WHERE a.ID_Tramite = t.ID_Tramite
                )
            ) THEN 'COMPLETA'
            ELSE 'EN PROCESO'
        END
    FROM Tramites t
    WHERE t.ID_Tramite = @idTramite;
    
    -- Si el estado es COMPLETA o ID_EstadoBasico = 1, retornar 0 días (no necesita reiteración)
    IF @statusReal = 'COMPLETA' OR @estadoBasico = 1
    BEGIN
        RETURN 0;
    END
    
    -- Obtener la fecha de la última reiteración o fecha RCHRP
    SELECT 
        @fechaInicio = COALESCE(
            (SELECT TOP 1 r.FechaReiteracion 
             FROM Reiteraciones r 
             WHERE r.ID_Tramite = @idTramite 
             ORDER BY r.NumeroReiteracion DESC), 
            t.FechaRCHRP
        )
    FROM Tramites t
    WHERE t.ID_Tramite = @idTramite;
    
    -- Si hay fecha de inicio, calcular días transcurridos
    IF @fechaInicio IS NOT NULL
    BEGIN
        SET @diasTranscurridos = DATEDIFF(day, @fechaInicio, GETDATE());
    END
    
    RETURN @diasTranscurridos;
END
GO
/****** Object:  Table [dbo].[TiposTramite]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[TiposTramite](
	[ID_TipoTramite] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[Descripcion] [nvarchar](255) NULL,
	[Activo] [bit] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_TipoTramite] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ClavesTramite]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ClavesTramite](
	[ID_ClaveTramite] [int] IDENTITY(1,1) NOT NULL,
	[Clave] [nvarchar](20) NOT NULL,
	[Descripcion] [nvarchar](255) NULL,
	[ID_TipoTramite] [int] NULL,
	[Activo] [bit] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_ClaveTramite] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Tramites]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Tramites](
	[ID_Tramite] [int] IDENTITY(1,1) NOT NULL,
	[ID_Promovente] [int] NULL,
	[CIIA] [nvarchar](13) NOT NULL,
	[FechaRegistro] [date] NOT NULL,
	[ID_TipoTramite] [int] NULL,
	[ID_ClaveTramite] [int] NULL,
	[ID_Municipio] [int] NULL,
	[ID_NucleoAgrario] [int] NULL,
	[Descripcion] [nvarchar](max) NULL,
	[FolioRCHRP] [nvarchar](50) NULL,
	[FechaRCHRP] [date] NULL,
	[ID_EstadoTramite] [int] NULL,
	[FechaUltimaActualizacion] [datetime] NULL,
	[ID_EstadoBasico] [int] NULL,
	[FechaCompletado] [date] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Tramite] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  View [dbo].[VistaTramites]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

    CREATE VIEW [dbo].[VistaTramites] AS
    SELECT 
        t.ID_Tramite,
        t.ID_Promovente,
        t.CIIA,
        t.FechaRegistro,
        t.ID_TipoTramite,
        t.ID_ClaveTramite,
        t.ID_Municipio,
        t.ID_NucleoAgrario,
        t.Descripcion AS DescripcionTramite,
        t.FolioRCHRP,
        t.FechaRCHRP,
        t.ID_EstadoTramite,
        t.FechaUltimaActualizacion,
        t.ID_EstadoBasico,
        tt.Nombre AS TipoTramite,
        c.Clave,
        c.Descripcion AS DescripcionClave
    FROM [dbo].[Tramites] t
    INNER JOIN [dbo].[ClavesTramite] c ON t.ID_ClaveTramite = c.ID_ClaveTramite
    INNER JOIN [dbo].[TiposTramite] tt ON c.ID_TipoTramite = tt.ID_TipoTramite
    WHERE tt.Activo = 1
GO
/****** Object:  Table [dbo].[Acuses]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Acuses](
	[ID_Acuse] [int] IDENTITY(1,1) NOT NULL,
	[ID_Tramite] [int] NULL,
	[NumeroAcuse] [nvarchar](11) NOT NULL,
	[FechaRecepcionRAN] [date] NULL,
	[NombreRevisor] [nvarchar](100) NULL,
	[FolioReloj] [nvarchar](20) NULL,
	[ID_EstadoTramite] [int] NULL,
	[Respuesta] [nvarchar](max) NULL,
	[FechaRegistro] [datetime] NULL,
	[EstadoDescriptivo] [varchar](50) NULL,
	[ID_EstadoBasico] [int] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Acuse] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Documentos]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Documentos](
	[ID_Documento] [int] IDENTITY(1,1) NOT NULL,
	[ID_TipoDocumento] [int] NOT NULL,
	[Nombre] [nvarchar](255) NOT NULL,
	[ID_Promovente] [int] NULL,
	[ID_Tramite] [int] NULL,
	[RutaArchivo] [nvarchar](255) NOT NULL,
	[FechaGeneracion] [datetime] NULL,
	[DatosDocumento] [nvarchar](max) NULL,
	[Estado] [nvarchar](20) NULL,
	[UsuarioGenerador] [nvarchar](100) NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Documento] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[EstadosBasicos]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[EstadosBasicos](
	[ID_EstadoBasico] [int] NOT NULL,
	[Nombre] [nvarchar](50) NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_EstadoBasico] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[EstadosDescriptivos]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[EstadosDescriptivos](
	[ID_EstadoDescriptivo] [varchar](50) NOT NULL,
	[Nombre] [nvarchar](500) NULL,
	[Descripcion] [varchar](255) NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_EstadoDescriptivo] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[EstadosTramite]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[EstadosTramite](
	[ID_EstadoTramite] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](50) NOT NULL,
	[Porcentaje] [int] NULL,
	[Descripcion] [nvarchar](255) NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_EstadoTramite] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[HistorialCambios]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[HistorialCambios](
	[ID_Historial] [int] IDENTITY(1,1) NOT NULL,
	[ID_Tramite] [int] NULL,
	[FechaCambio] [datetime] NULL,
	[EstadoAnterior] [int] NULL,
	[EstadoNuevo] [int] NULL,
	[Observacion] [nvarchar](max) NULL,
	[UsuarioResponsable] [nvarchar](100) NULL,
	[TipoAccion] [varchar](50) NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Historial] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Municipios]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Municipios](
	[ID_Municipio] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[Estado] [nvarchar](100) NULL,
	[Activo] [bit] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Municipio] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_Municipios_Nombre] UNIQUE NONCLUSTERED 
(
	[Nombre] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[NucleosAgrarios]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[NucleosAgrarios](
	[ID_NucleoAgrario] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[ID_Municipio] [int] NULL,
	[ID_TipoNucleoAgrario] [char](1) NULL,
	[Activo] [bit] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_NucleoAgrario] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_NucleosAgrarios_Nombre_Municipio_Tipo] UNIQUE NONCLUSTERED 
(
	[Nombre] ASC,
	[ID_Municipio] ASC,
	[ID_TipoNucleoAgrario] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Promoventes]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Promoventes](
	[ID_Promovente] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[ApellidoPaterno] [nvarchar](100) NULL,
	[ApellidoMaterno] [nvarchar](100) NULL,
	[Telefono] [nvarchar](20) NULL,
	[CorreoElectronico] [nvarchar](100) NULL,
	[Direccion] [nvarchar](255) NULL,
	[FechaRegistro] [datetime] NULL,
	[Activo] [bit] NULL,
	[Telefono2] [nvarchar](20) NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Promovente] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Reiteraciones]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Reiteraciones](
	[ID_Reiteracion] [int] IDENTITY(1,1) NOT NULL,
	[ID_Tramite] [int] NULL,
	[FolioReiteracion] [nvarchar](50) NOT NULL,
	[FechaReiteracion] [date] NOT NULL,
	[NumeroReiteracion] [int] NOT NULL,
	[Observaciones] [nvarchar](max) NULL,
	[FechaRegistro] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Reiteracion] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Subsanaciones]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Subsanaciones](
	[ID_Subsanacion] [int] IDENTITY(1,1) NOT NULL,
	[ID_Tramite] [int] NULL,
	[FolioSubsanacion] [nvarchar](50) NOT NULL,
	[FechaSubsanacion] [date] NOT NULL,
	[Descripcion] [nvarchar](max) NULL,
	[FechaRegistro] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_Subsanacion] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[TiposDocumento]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[TiposDocumento](
	[ID_TipoDocumento] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[Descripcion] [nvarchar](255) NULL,
	[Categoria] [nvarchar](50) NOT NULL,
	[RutaPlantilla] [nvarchar](255) NULL,
	[Activo] [bit] NULL,
	[CamposRequeridos] [nvarchar](max) NULL,
	[FechaCreacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_TipoDocumento] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[TiposNucleoAgrario]    Script Date: 23/04/2025 10:59:28 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[TiposNucleoAgrario](
	[ID_TipoNucleoAgrario] [char](1) NOT NULL,
	[Descripcion] [nvarchar](50) NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[ID_TipoNucleoAgrario] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
ALTER TABLE [dbo].[Acuses] ADD  DEFAULT (getdate()) FOR [FechaRegistro]
GO
ALTER TABLE [dbo].[ClavesTramite] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Documentos] ADD  DEFAULT (getdate()) FOR [FechaGeneracion]
GO
ALTER TABLE [dbo].[Documentos] ADD  DEFAULT ('FINALIZADO') FOR [Estado]
GO
ALTER TABLE [dbo].[Documentos] ADD  DEFAULT ('Sistema') FOR [UsuarioGenerador]
GO
ALTER TABLE [dbo].[HistorialCambios] ADD  DEFAULT (getdate()) FOR [FechaCambio]
GO
ALTER TABLE [dbo].[Municipios] ADD  DEFAULT ('Guerrero') FOR [Estado]
GO
ALTER TABLE [dbo].[Municipios] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[NucleosAgrarios] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Promoventes] ADD  DEFAULT (getdate()) FOR [FechaRegistro]
GO
ALTER TABLE [dbo].[Promoventes] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Reiteraciones] ADD  DEFAULT (getdate()) FOR [FechaRegistro]
GO
ALTER TABLE [dbo].[Subsanaciones] ADD  DEFAULT (getdate()) FOR [FechaRegistro]
GO
ALTER TABLE [dbo].[TiposDocumento] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[TiposDocumento] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[TiposTramite] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Tramites] ADD  DEFAULT ((1)) FOR [ID_EstadoTramite]
GO
ALTER TABLE [dbo].[Tramites] ADD  DEFAULT (getdate()) FOR [FechaUltimaActualizacion]
GO
ALTER TABLE [dbo].[Acuses]  WITH NOCHECK ADD FOREIGN KEY([ID_EstadoTramite])
REFERENCES [dbo].[EstadosTramite] ([ID_EstadoTramite])
GO
ALTER TABLE [dbo].[Acuses]  WITH NOCHECK ADD FOREIGN KEY([ID_Tramite])
REFERENCES [dbo].[Tramites] ([ID_Tramite])
GO
ALTER TABLE [dbo].[Acuses]  WITH NOCHECK ADD  CONSTRAINT [FK_Acuses_EstadosBasicos] FOREIGN KEY([ID_EstadoBasico])
REFERENCES [dbo].[EstadosBasicos] ([ID_EstadoBasico])
GO
ALTER TABLE [dbo].[Acuses] CHECK CONSTRAINT [FK_Acuses_EstadosBasicos]
GO
ALTER TABLE [dbo].[ClavesTramite]  WITH NOCHECK ADD FOREIGN KEY([ID_TipoTramite])
REFERENCES [dbo].[TiposTramite] ([ID_TipoTramite])
GO
ALTER TABLE [dbo].[Documentos]  WITH CHECK ADD FOREIGN KEY([ID_Promovente])
REFERENCES [dbo].[Promoventes] ([ID_Promovente])
GO
ALTER TABLE [dbo].[Documentos]  WITH CHECK ADD FOREIGN KEY([ID_TipoDocumento])
REFERENCES [dbo].[TiposDocumento] ([ID_TipoDocumento])
GO
ALTER TABLE [dbo].[Documentos]  WITH CHECK ADD FOREIGN KEY([ID_Tramite])
REFERENCES [dbo].[Tramites] ([ID_Tramite])
GO
ALTER TABLE [dbo].[HistorialCambios]  WITH NOCHECK ADD FOREIGN KEY([ID_Tramite])
REFERENCES [dbo].[Tramites] ([ID_Tramite])
GO
ALTER TABLE [dbo].[NucleosAgrarios]  WITH NOCHECK ADD FOREIGN KEY([ID_Municipio])
REFERENCES [dbo].[Municipios] ([ID_Municipio])
GO
ALTER TABLE [dbo].[NucleosAgrarios]  WITH NOCHECK ADD FOREIGN KEY([ID_TipoNucleoAgrario])
REFERENCES [dbo].[TiposNucleoAgrario] ([ID_TipoNucleoAgrario])
GO
ALTER TABLE [dbo].[Reiteraciones]  WITH NOCHECK ADD FOREIGN KEY([ID_Tramite])
REFERENCES [dbo].[Tramites] ([ID_Tramite])
GO
ALTER TABLE [dbo].[Subsanaciones]  WITH NOCHECK ADD FOREIGN KEY([ID_Tramite])
REFERENCES [dbo].[Tramites] ([ID_Tramite])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD FOREIGN KEY([ID_ClaveTramite])
REFERENCES [dbo].[ClavesTramite] ([ID_ClaveTramite])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD FOREIGN KEY([ID_EstadoTramite])
REFERENCES [dbo].[EstadosTramite] ([ID_EstadoTramite])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD FOREIGN KEY([ID_Municipio])
REFERENCES [dbo].[Municipios] ([ID_Municipio])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD FOREIGN KEY([ID_NucleoAgrario])
REFERENCES [dbo].[NucleosAgrarios] ([ID_NucleoAgrario])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD FOREIGN KEY([ID_Promovente])
REFERENCES [dbo].[Promoventes] ([ID_Promovente])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD FOREIGN KEY([ID_TipoTramite])
REFERENCES [dbo].[TiposTramite] ([ID_TipoTramite])
GO
ALTER TABLE [dbo].[Tramites]  WITH NOCHECK ADD  CONSTRAINT [FK_Tramites_EstadosBasicos] FOREIGN KEY([ID_EstadoBasico])
REFERENCES [dbo].[EstadosBasicos] ([ID_EstadoBasico])
GO
ALTER TABLE [dbo].[Tramites] CHECK CONSTRAINT [FK_Tramites_EstadosBasicos]
GO
ALTER TABLE [dbo].[Reiteraciones]  WITH NOCHECK ADD CHECK  (([NumeroReiteracion]>=(1) AND [NumeroReiteracion]<=(3)))
GO
/****** Object:  StoredProcedure [dbo].[InsertarNucleoAgrarioSeguro]    Script Date: 23/04/2025 10:59:29 p. m. ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
-- Procedimiento seguro para insertar núcleos agrarios
CREATE   PROCEDURE [dbo].[InsertarNucleoAgrarioSeguro]
    @NombreMunicipio NVARCHAR(100),
    @NombreNucleo NVARCHAR(100),
    @TipoNucleo CHAR(1)
AS
BEGIN
    DECLARE @MunicipioId INT
    DECLARE @TipoNucleoId CHAR(1) = @TipoNucleo
    
    -- Obtener ID del municipio
    SELECT @MunicipioId = [ID_Municipio] 
    FROM [dbo].[Municipios] 
    WHERE [Nombre] = @NombreMunicipio
    
    IF @MunicipioId IS NULL
    BEGIN
        PRINT 'Municipio no encontrado: ' + @NombreMunicipio
        RETURN
    END
    
    -- Verificar si ya existe el mismo núcleo con el mismo tipo
    IF NOT EXISTS (
        SELECT 1 
        FROM [dbo].[NucleosAgrarios] 
        WHERE [Nombre] = @NombreNucleo 
          AND [ID_Municipio] = @MunicipioId
          AND [ID_TipoNucleoAgrario] = @TipoNucleoId
    )
    BEGIN
        -- Intentar insertar
        BEGIN TRY
            INSERT INTO [dbo].[NucleosAgrarios] ([Nombre], [ID_Municipio], [ID_TipoNucleoAgrario])
            VALUES (@NombreNucleo, @MunicipioId, @TipoNucleoId)
            
            PRINT 'Insertado: ' + @NombreNucleo + ' (' + @TipoNucleoId + ') en ' + @NombreMunicipio
        END TRY
        BEGIN CATCH
            PRINT 'Error al insertar ' + @NombreNucleo + ' en ' + @NombreMunicipio + ': ' + ERROR_MESSAGE()
        END CATCH
    END
    ELSE
    BEGIN
        PRINT 'Ya existe: ' + @NombreNucleo + ' (' + @TipoNucleoId + ') en ' + @NombreMunicipio
    END
END
GO
USE [master]
GO
ALTER DATABASE [SistemaGestorTramitesAgrarios] SET  READ_WRITE 
GO
