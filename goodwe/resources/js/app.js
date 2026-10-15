import {
    createIcons, House, Zap, Wallet, History, CircleHelp, User, LogOut, Bell,
    Search, MapPin, CalendarClock, Star, ArrowRight, ArrowLeft, ArrowUpRight,
    ArrowDownLeft, ChevronRight, Gift, BatteryCharging, SmartphoneNfc, Gem,
    ChevronDown, MessageCircle, X, Send, Bot, Menu, Headset, ChartNoAxesColumn,
    PlugZap, PlusCircle, CarFront, Plus, Cable, Receipt, Sparkles, TicketPercent,
    Lightbulb, ShieldCheck, Check, Trash2, Moon, Languages, Info, ArrowDown, Store, LayoutDashboard
} from 'lucide';
import Chart from 'chart.js/auto';
import './portal';
import './manager';
import './navigation';
import './commands';
import './dashboard';
import './web-charging';

createIcons({ icons: {
    House, Zap, Wallet, History, CircleHelp, User, LogOut, Bell, Search, MapPin,
    CalendarClock, Star, ArrowRight, ArrowLeft, ArrowUpRight, ArrowDownLeft,
    ChevronRight, Gift, BatteryCharging, SmartphoneNfc, Gem, ChevronDown,
    MessageCircle, X, Send, Bot, Menu, Headset, ChartNoAxesColumn, PlugZap,
    PlusCircle, CarFront, Plus, Cable, Receipt, Sparkles, TicketPercent,
    Lightbulb, ShieldCheck, Check, Trash2, Moon, Languages, Info, ArrowDown, Store, LayoutDashboard
} });
window.Chart = Chart;
