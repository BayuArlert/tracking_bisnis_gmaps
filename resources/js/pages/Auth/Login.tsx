import React, { useState, useContext, ChangeEvent, FormEvent } from "react";
// Update the path below to the correct location of AuthContext
import { AuthContext } from "../../context/AuthContext";
import axios from "axios";
import { toast } from "sonner";
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useNavigate } from "react-router-dom"
import { Card } from "@/components/ui/card"
import { Eye, EyeOff } from "lucide-react"; // 👁️ icon

interface FormData {
  username: string;
  email: string;
  password: string;
}



const Login: React.FC = () => {
  const { login, API } = useContext(AuthContext) as {
    login: (token: string, user: unknown) => void;
    API: string;
  };

  const [isLogin, setIsLogin] = useState<boolean>(true);
  const [formData, setFormData] = useState<FormData>({
    username: "",
    email: "",
    password: "",
  });
  const [loading, setLoading] = useState<boolean>(false);
  const [showPassword, setShowPassword] = useState<boolean>(false);

  const handleChange = (e: ChangeEvent<HTMLInputElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
  };

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);

    try {
      if (isLogin) {
        const response = await axios.post(`${API}/auth/login`, {
          username: formData.username,
          password: formData.password,
        });

        login(response.data.access_token, response.data.user);
        toast.success("Login berhasil!");
      } else {
        await axios.post(`${API}/auth/register`, formData);
        toast.success("Registrasi berhasil! Silakan login.");
        setIsLogin(true);
        setFormData({ username: "", email: "", password: "" });
      }
    } catch (error: any) {
      toast.error(error.response?.data?.detail || "Terjadi kesalahan");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-cyan-50 flex items-center justify-center p-4">
      <Card className="w-full max-w-md relative z-10 p-8 shadow-2xl border-0 bg-white/80 backdrop-blur-xl">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-lg">
            <svg
              className="w-8 h-8 text-white"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          </div>
          <h1 className="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
            TrendBuss
          </h1>
          <p className="text-gray-600 mt-2">
            Dashboard Monitoring Bisnis Google Maps
          </p>
        </div>

        {/* Tabs */}
        <div className="flex bg-gray-100 rounded-xl p-1 mb-6">
          <button
            type="button"
            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-all duration-300 ${isLogin
                ? "bg-white text-blue-600 shadow-sm"
                : "text-gray-500 hover:text-gray-700"
              }`}
            onClick={() => setIsLogin(true)}
            data-testid="login-tab"
          >
            Masuk
          </button>
          <button
            type="button"
            className={`flex-1 py-2 px-4 rounded-lg font-medium transition-all duration-300 ${!isLogin
                ? "bg-white text-blue-600 shadow-sm"
                : "text-gray-500 hover:text-gray-700"
              }`}
            onClick={() => setIsLogin(false)}
            data-testid="register-tab"
          >
            Daftar
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Username */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700">Username</label>
            <Input
              type="text"
              name="username"
              value={formData.username}
              onChange={handleChange}
              placeholder="Masukkan username"
              required
              className="h-12 border-gray-200 focus:border-blue-400 focus:ring-blue-400"
            />
          </div>

          {/* Email (hanya untuk register) */}
          {!isLogin && (
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">Email</label>
              <Input
                type="email"
                name="email"
                value={formData.email}
                onChange={handleChange}
                placeholder="Masukkan email"
                required
                className="h-12 border-gray-200 focus:border-blue-400 focus:ring-blue-400"
              />
            </div>
          )}

          {/* Password */}
          <div className="space-y-2 relative">
            <label className="text-sm font-medium text-gray-700">Password</label>
            <div className="relative">
              <Input
                type={showPassword ? "text" : "password"}
                name="password"
                value={formData.password}
                onChange={handleChange}
                placeholder="Masukkan password"
                required
                className="h-12 border-gray-200 focus:border-blue-400 focus:ring-blue-400 pr-10"
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
              >
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
            </div>
          </div>

          {/* Submit */}
          <Button
            type="submit"
            disabled={loading}
            className="w-full h-12 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5"
          >
            {loading ? (
              <div className="flex items-center space-x-2">
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>Loading...</span>
              </div>
            ) : isLogin ? (
              "Masuk"
            ) : (
              "Daftar"
            )}
          </Button>
        </form>
      </Card>
    </div>
  );
};

export default Login;
