<nav class="uk-navbar-container uk-box-shadow-small" uk-navbar>
    <div class="uk-navbar-left">
        <a href="{{ route('dashboard') }}" class="uk-navbar-item uk-logo">
            <x-application-logo />
        </a>

        <ul class="uk-navbar-nav uk-visible@m">
            <li class="{{ request()->routeIs('dashboard') ? 'uk-active' : '' }}">
                <a href="{{ route('dashboard') }}">Panel</a>
            </li>
            <li class="{{ request()->routeIs('documents.index') || request()->routeIs('documents.show') ? 'uk-active' : '' }}">
                <a href="{{ route('documents.index') }}">Personas registradas</a>
            </li>
            <li class="{{ request()->routeIs('documents.create') ? 'uk-active' : '' }}">
                <a href="{{ route('documents.create') }}">Subir documento</a>
            </li>
        </ul>
    </div>

    <div class="uk-navbar-right">
        <ul class="uk-navbar-nav uk-visible@m">
            <li>
                <a href="#">
                    <span uk-icon="icon: user"></span>&nbsp;{{ Auth::user()->name }}
                </a>
                <div class="uk-navbar-dropdown">
                    <ul class="uk-nav uk-navbar-dropdown-nav">
                        <li><a href="{{ route('profile.edit') }}">Mi perfil</a></li>
                        <li><a href="{{ route('register') }}">Agregar usuario</a></li>
                        <li class="uk-nav-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">
                                    Cerrar sesión
                                </a>
                            </form>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

        <a href="#panel-movil" class="uk-navbar-toggle uk-hidden@m" uk-toggle uk-navbar-toggle-icon></a>
    </div>
</nav>

<div id="panel-movil" uk-offcanvas="overlay: true">
    <div class="uk-offcanvas-bar">
        <button class="uk-offcanvas-close" type="button" uk-close></button>

        <ul class="uk-nav uk-nav-default uk-margin-top">
            <li class="{{ request()->routeIs('dashboard') ? 'uk-active' : '' }}"><a href="{{ route('dashboard') }}">Panel</a></li>
            <li class="{{ request()->routeIs('documents.index') ? 'uk-active' : '' }}"><a href="{{ route('documents.index') }}">Personas registradas</a></li>
            <li class="{{ request()->routeIs('documents.create') ? 'uk-active' : '' }}"><a href="{{ route('documents.create') }}">Subir documento</a></li>
            <li class="uk-nav-divider"></li>
            <li class="uk-nav-header">{{ Auth::user()->name }}</li>
            <li><a href="{{ route('profile.edit') }}">Mi perfil</a></li>
            <li><a href="{{ route('register') }}">Agregar usuario</a></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();">Cerrar sesión</a>
                </form>
            </li>
        </ul>
    </div>
</div>
